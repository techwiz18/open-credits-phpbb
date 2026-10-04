<?php
/**
 * OpenCredits for phpBB — UCP wallet: history + transfers.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\ucp;

class main_module
{
    public $page_title;
    public $tpl_name;
    public $u_action;

    /** @var \techwiz18\opencredits\service\transact */
    protected $transact;

    public function main($id, $mode)
    {
        global $user, $auth, $template, $request, $phpbb_container;

        $this->transact = $phpbb_container->get('techwiz18.opencredits.transact');

        switch ($mode)
        {
            case 'transfer':
                $this->page_title = $user->lang('UCP_OC_TRANSFER');
                $this->handle_transfer($user, $auth, $template, $request);
            break;

            case 'history':
            default:
                $this->page_title = $user->lang('UCP_OC_HISTORY');
                $this->show_history($user, $auth, $template, $request, $phpbb_container);
            break;
        }

        $this->tpl_name = 'ucp_oc_wallet';
    }

    protected function show_history($user, $auth, $template, $request, $phpbb_container)
    {
        if (!$auth->acl_get('u_oc_view'))
        {
            trigger_error('NO_AUTH_OPERATION');
        }

        $user_id = (int) $user->data['user_id'];
        $start = $request->variable('start', 0);
        $limit = 20;

        $total = $this->transact->history_count($user_id);
        $rows = $this->transact->user_history($user_id, $limit, $start);
        $currencies = $this->currency_map();

        foreach ($rows as $row)
        {
            $currency_id = (int) $row['currency_id'];
            $template->assign_block_vars('history', [
                'AMOUNT'    => $this->format_amount($currencies, $currency_id, $row['amount']),
                'TRIGGER'   => $row['trigger_name'],
                'NOTE'      => $row['note'],
                'TIME'      => $user->format_date((int) $row['log_time']),
            ]);
        }

        $pagination = $phpbb_container->get('pagination');
        $pagination->generate_template_pagination($this->u_action, 'pagination', 'start', $total, $limit, $start);

        $template->assign_vars([
            'S_OC_HISTORY'  => true,
            'TOTAL_ROWS'    => $total,
            'OC_PAGE_TITLE' => $user->lang('UCP_OC_HISTORY'),
        ]);
    }

    protected function handle_transfer($user, $auth, $template, $request)
    {
        if (!$auth->acl_get('u_oc_transfer'))
        {
            trigger_error('NO_AUTH_OPERATION');
        }

        $user_id = (int) $user->data['user_id'];
        $currencies = $this->transact->active_currencies();
        $errors = [];
        $recipient_default = '';
        $amount_default = '';

        // Donate links land here with ?oc_to=<user_id>; prefill the recipient.
        $to_id = $request->variable('oc_to', 0);
        if ($to_id > 0)
        {
            $to_name = $this->find_username($to_id);
            if ($to_name !== '' && $to_id !== $user_id)
            {
                $recipient_default = $to_name;
            }
        }

        foreach ($currencies as $currency)
        {
            $currency_id = (int) $currency['currency_id'];
            $template->assign_block_vars('currencies', [
                'ID'        => $currency_id,
                'NAME'      => $currency['title'],
                'BALANCE'   => $this->format_amount($this->currency_map(), $currency_id, $this->transact->get_balance($user_id, $currency_id)),
            ]);
        }

        if ($request->is_set_post('oc_transfer'))
        {
            if (!check_form_key('oc_transfer'))
            {
                trigger_error('FORM_INVALID');
            }

            $recipient_name = $request->variable('oc_recipient', '', true);
            $amount_raw = $request->variable('oc_amount', '');
            $currency_id = $request->variable('oc_currency', 0);
            $recipient_default = $recipient_name;
            $amount_default = $amount_raw;

            $currency_ids = array_map(function ($c) { return (int) $c['currency_id']; }, $currencies);
            if (!in_array($currency_id, $currency_ids, true))
            {
                $errors[] = $user->lang('OC_TRANSFER_BAD_CURRENCY');
            }
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount_raw) || (float) $amount_raw <= 0)
            {
                $errors[] = $user->lang('OC_TRANSFER_BAD_AMOUNT');
            }

            $recipient_id = $this->find_user_id($recipient_name);
            if ($recipient_id === 0)
            {
                $errors[] = $user->lang('OC_TRANSFER_BAD_USER');
            }
            else if ($recipient_id === $user_id)
            {
                $errors[] = $user->lang('OC_TRANSFER_SELF');
            }

            if (empty($errors))
            {
                $note = $user->data['username'] . ' -> ' . $recipient_name;
                if ($this->transact->transfer($user_id, $recipient_id, $currency_id, $amount_raw, $note))
                {
                    $redirect = $this->u_action;
                    meta_refresh(3, $redirect);
                    trigger_error($user->lang('OC_TRANSFER_SUCCESS'));
                }
                $errors[] = $user->lang('OC_TRANSFER_FUNDS');
            }
        }

        add_form_key('oc_transfer');
        $template->assign_vars([
            'S_OC_TRANSFER'     => true,
            'OC_PAGE_TITLE'     => $user->lang('UCP_OC_TRANSFER'),
            'ERROR'             => implode('<br>', $errors),
            'OC_RECIPIENT'      => $recipient_default,
            'OC_AMOUNT'         => $amount_default,
            'S_FORM_ACTION'     => $this->u_action,
        ]);
    }

    /** username → user_id; 0 when missing. Rejects bots/guests. */
    protected function find_user_id($username)
    {
        global $db;
        $clean = utf8_clean_string($username);
        if ($clean === '')
        {
            return 0;
        }
        $sql = 'SELECT user_id, user_type FROM ' . USERS_TABLE . "
            WHERE username_clean = '" . $db->sql_escape($clean) . "'";
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        if (!$row || (int) $row['user_id'] === (int) ANONYMOUS || (int) $row['user_type'] === (int) USER_IGNORE)
        {
            return 0;
        }
        return (int) $row['user_id'];
    }

    /** user_id → username; '' when missing or not receivable (bots/guests). */
    protected function find_username($user_id)
    {
        global $db;
        $user_id = (int) $user_id;
        if ($user_id <= 0)
        {
            return '';
        }
        $sql = 'SELECT username, user_type FROM ' . USERS_TABLE . '
            WHERE user_id = ' . $user_id;
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        if (!$row || $user_id === (int) ANONYMOUS || (int) $row['user_type'] === (int) USER_IGNORE)
        {
            return '';
        }
        return (string) $row['username'];
    }

    protected function currency_map()
    {
        $map = [];
        foreach ($this->transact->active_currencies() as $currency)
        {
            $map[(int) $currency['currency_id']] = $currency;
        }
        return $map;
    }

    protected function format_amount($map, $currency_id, $amount)
    {
        if (!isset($map[$currency_id]))
        {
            return (string) $amount;
        }
        return $map[$currency_id]['prefix'] . $amount . $map[$currency_id]['suffix'];
    }
}
