<?php
/**
 * OpenCredits for phpBB — Transact service.
 *
 * THE ONLY place that writes balances. All earning paths (triggers) and
 * transfers funnel through here. Money math runs in integer minor units
 * (cents) to avoid float error; storage stays DECIMAL(10,2) strings.
 *
 * Rules (mirrored from the XenForo twin, re-implemented):
 *  - award_by_trigger() pays EVERY active event row for the trigger, so
 *    overlapping per-currency events stack.
 *  - adjust() is unrestricted (awards + admin tooling).
 *  - transfer() is double-entry, floored at zero, and NEVER overdraws:
 *    insufficient sender balance refuses the whole transfer.
 *  - Missing balance row means 0.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\service;

class transact
{
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var string */
    protected $table_prefix;

    /** @var array Per-request cache of active event rows by trigger name */
    protected $event_cache = [];

    public function __construct(\phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->db = $db;
        $this->table_prefix = $table_prefix;
    }

    /**
     * Server-local start of today (unix timestamp). Awards use this as the
     * day boundary for max_per_day / exactly-once daily checks.
     */
    public function day_start()
    {
        return (int) mktime(0, 0, 0);
    }

    /**
     * Pay every active event row registered for $trigger_name to $user_id.
     * Returns the number of event rows paid (0 when none apply).
     */
    public function award_by_trigger($trigger_name, $user_id, $content_id = 0, $forum_id = 0, $note = '')
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0)
        {
            return 0;
        }

        $paid = 0;
        $today = $this->day_start();
        foreach ($this->active_events($trigger_name) as $event)
        {
            if (!$this->forum_allowed($event, (int) $forum_id))
            {
                continue;
            }
            if (!$this->within_daily_limit($event, $user_id, $today))
            {
                continue;
            }
            $this->adjust((int) $event['currency_id'], $user_id, (string) $event['amount'], $trigger_name, (int) $content_id, (string) $note);
            $paid++;
        }
        return $paid;
    }

    /**
     * Unrestricted adjustment: append ledger row + bump cached balance.
     * Positive or negative; caller decides policy. Transfers must NOT use
     * this — they go through transfer().
     */
    public function adjust($currency_id, $user_id, $amount, $trigger_name, $content_id = 0, $note = '')
    {
        $this->db->sql_transaction('begin');
        try
        {
            $this->insert_ledger((int) $currency_id, (int) $user_id, $amount, $trigger_name, (int) $content_id, (string) $note);
            $this->bump_balance((int) $currency_id, (int) $user_id, $amount);
            $this->db->sql_transaction('commit');
        }
        catch (\Exception $e)
        {
            $this->db->sql_transaction('rollback');
            throw $e;
        }
    }

    /**
     * Double-entry transfer. Writes a debit row for the sender and a credit
     * row for the recipient atomically. Returns true on success, false when
     * refused (bad amount, self-transfer, or insufficient funds — the sender
     * can never be taken below zero by a transfer).
     */
    public function transfer($from_user_id, $to_user_id, $currency_id, $amount, $note = '')
    {
        $from_user_id = (int) $from_user_id;
        $to_user_id = (int) $to_user_id;
        $currency_id = (int) $currency_id;
        $cents = $this->to_cents($amount);

        if ($from_user_id <= 0 || $to_user_id <= 0 || $from_user_id === $to_user_id || $cents <= 0)
        {
            return false;
        }

        $this->db->sql_transaction('begin');
        try
        {
            $sender_balance = $this->locked_balance($currency_id, $from_user_id);
            $sender_balance = $sender_balance === null ? '0.00' : $sender_balance;
            if ($this->to_cents($sender_balance) < $cents)
            {
                $this->db->sql_transaction('rollback');
                return false;
            }

            $decimal = $this->to_decimal($cents);
            $this->insert_ledger($currency_id, $from_user_id, '-' . $decimal, 'transfer_out', $to_user_id, (string) $note);
            $this->bump_balance($currency_id, $from_user_id, '-' . $decimal);
            $this->insert_ledger($currency_id, $to_user_id, $decimal, 'transfer_in', $from_user_id, (string) $note);
            $this->bump_balance($currency_id, $to_user_id, $decimal);

            $this->db->sql_transaction('commit');
            return true;
        }
        catch (\Exception $e)
        {
            $this->db->sql_transaction('rollback');
            throw $e;
        }
    }

    /**
     * Current cached balance as a '0.00'-style string. Missing row means 0.
     */
    public function get_balance($user_id, $currency_id)
    {
        $sql = 'SELECT balance FROM ' . $this->table_prefix . 'oc_balance
            WHERE user_id = ' . (int) $user_id . ' AND currency_id = ' . (int) $currency_id;
        $result = $this->db->sql_query($sql);
        $balance = $this->db->sql_fetchfield('balance');
        $this->db->sql_freeresult($result);
        return $balance === false ? '0.00' : (string) $balance;
    }

    /**
     * True when the user already has a ledger row for this trigger today
     * (any currency). Used for exactly-once guards like the daily login.
     */
    public function has_award_today($user_id, $trigger_name, $today = null)
    {
        $today = $today === null ? $this->day_start() : (int) $today;
        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->table_prefix . 'oc_transaction
            WHERE user_id = ' . (int) $user_id . '
                AND trigger_name = \'' . $this->db->sql_escape($trigger_name) . '\'
                AND log_time >= ' . $today;
        $result = $this->db->sql_query($sql);
        $count = (int) $this->db->sql_fetchfield('cnt');
        $this->db->sql_freeresult($result);
        return $count > 0;
    }

    /**
     * Rebuild every cached balance from the ledger (admin/CLI tooling).
     * Returns the number of balance rows written.
     */
    public function rebuild_all_balances()
    {
        $this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'oc_balance');
        $sql = 'INSERT INTO ' . $this->table_prefix . 'oc_balance (user_id, currency_id, balance)
            SELECT user_id, currency_id, SUM(amount)
            FROM ' . $this->table_prefix . 'oc_transaction
            GROUP BY user_id, currency_id';
        $this->db->sql_query($sql);
        return $this->db->sql_affectedrows();
    }

    /**
     * All active currencies, primary first. Read-only; safe for display.
     */
    public function active_currencies()
    {
        $sql = 'SELECT * FROM ' . $this->table_prefix . 'oc_currency
            WHERE active = 1
            ORDER BY is_primary DESC, currency_id ASC';
        $result = $this->db->sql_query($sql);
        $rows = $this->db->sql_fetchrowset($result);
        $this->db->sql_freeresult($result);
        return $rows;
    }

    /**
     * Ledger row count for a user (history pagination total).
     */
    public function history_count($user_id)
    {
        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->table_prefix . 'oc_transaction
            WHERE user_id = ' . (int) $user_id;
        $result = $this->db->sql_query($sql);
        $count = (int) $this->db->sql_fetchfield('cnt');
        $this->db->sql_freeresult($result);
        return $count;
    }

    /**
     * Ledger rows for a user, newest first. Read-only; safe for display.
     */
    public function user_history($user_id, $limit, $start)
    {
        $sql = 'SELECT * FROM ' . $this->table_prefix . 'oc_transaction
            WHERE user_id = ' . (int) $user_id . '
            ORDER BY transaction_id DESC';
        $result = $this->db->sql_query_limit($sql, (int) $limit, (int) $start);
        $rows = $this->db->sql_fetchrowset($result);
        $this->db->sql_freeresult($result);
        return $rows;
    }

    /* ---------------- internals ---------------- */

    protected function active_events($trigger_name)
    {
        if (!isset($this->event_cache[$trigger_name]))
        {
            $sql = 'SELECT * FROM ' . $this->table_prefix . 'oc_event
                WHERE trigger_name = \'' . $this->db->sql_escape($trigger_name) . '\'
                    AND active = 1';
            $result = $this->db->sql_query($sql);
            $this->event_cache[$trigger_name] = $this->db->sql_fetchrowset($result);
            $this->db->sql_freeresult($result);
        }
        return $this->event_cache[$trigger_name];
    }

    protected function forum_allowed($event, $forum_id)
    {
        $allowed = trim((string) $event['forum_ids']);
        if ($allowed === '')
        {
            return true;
        }
        return in_array((string) $forum_id, explode(',', $allowed), true);
    }

    protected function within_daily_limit($event, $user_id, $today)
    {
        $max = (int) $event['max_per_day'];
        if ($max <= 0)
        {
            return true;
        }
        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->table_prefix . 'oc_transaction
            WHERE user_id = ' . (int) $user_id . '
                AND currency_id = ' . (int) $event['currency_id'] . '
                AND trigger_name = \'' . $this->db->sql_escape($event['trigger_name']) . '\'
                AND log_time >= ' . (int) $today;
        $result = $this->db->sql_query($sql);
        $count = (int) $this->db->sql_fetchfield('cnt');
        $this->db->sql_freeresult($result);
        return $count < $max;
    }

    /** Balance row locked for update inside the caller's transaction. Null when missing. */
    protected function locked_balance($currency_id, $user_id)
    {
        $sql = 'SELECT balance FROM ' . $this->table_prefix . 'oc_balance
            WHERE user_id = ' . (int) $user_id . ' AND currency_id = ' . (int) $currency_id . '
            FOR UPDATE';
        $result = $this->db->sql_query($sql);
        $balance = $this->db->sql_fetchfield('balance');
        $this->db->sql_freeresult($result);
        return $balance === false ? null : (string) $balance;
    }

    /** Add $amount (signed decimal string) to the cached balance (upsert). */
    protected function bump_balance($currency_id, $user_id, $amount)
    {
        $current = $this->locked_balance($currency_id, $user_id);
        $base = $current === null ? 0 : $this->to_cents($current);
        $new = $this->to_decimal($base + $this->to_cents($amount));
        if ($current === null)
        {
            $sql = 'INSERT INTO ' . $this->table_prefix . 'oc_balance ' . $this->db->sql_build_array('INSERT', [
                'user_id'       => (int) $user_id,
                'currency_id'   => (int) $currency_id,
                'balance'       => $new,
            ]);
            $this->db->sql_query($sql);
            return;
        }
        $sql = 'UPDATE ' . $this->table_prefix . 'oc_balance
            SET balance = \'' . $this->db->sql_escape($new) . '\'
            WHERE user_id = ' . (int) $user_id . ' AND currency_id = ' . (int) $currency_id;
        $this->db->sql_query($sql);
    }

    protected function insert_ledger($currency_id, $user_id, $amount, $trigger_name, $content_id, $note)
    {
        $sql = 'INSERT INTO ' . $this->table_prefix . 'oc_transaction ' . $this->db->sql_build_array('INSERT', [
            'user_id'       => (int) $user_id,
            'currency_id'   => (int) $currency_id,
            'amount'        => $this->to_decimal($this->to_cents($amount)),
            'trigger_name'  => substr((string) $trigger_name, 0, 64),
            'content_id'    => (int) $content_id,
            'note'          => substr((string) $note, 0, 255),
            'log_time'      => time(),
        ]);
        $this->db->sql_query($sql);
    }

    /** '12.34' / '-5' / 7 → cents int. */
    protected function to_cents($amount)
    {
        return (int) round((float) $amount * 100);
    }

    /** cents int → '12.34' / '-5.00'. */
    protected function to_decimal($cents)
    {
        $cents = (int) $cents;
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        return $sign . (int) ($cents / 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
