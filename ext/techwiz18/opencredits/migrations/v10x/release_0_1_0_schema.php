<?php
/**
 * OpenCredits for phpBB — v0.1.0 schema: currency / balance / event / ledger.
 *
 * Amounts use signed DECIMAL:10 (decimal(10,2)). The UINT family is UNSIGNED
 * in phpBB DBAL and must never back an amount/balance column.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\migrations\v10x;

class release_0_1_0_schema extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'oc_currency');
    }

    public static function depends_on()
    {
        return ['\phpbb\db\migration\data\v330\v330'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'oc_currency' => [
                    'COLUMNS' => [
                        'currency_id'   => ['UINT', null, 'auto_increment'],
                        'title'         => ['VCHAR:255', ''],
                        'prefix'        => ['VCHAR:16', ''],
                        'suffix'        => ['VCHAR:16', ''],
                        'decimals'      => ['USINT', 2],
                        'allow_negative'=> ['BOOL', 0],
                        'active'        => ['BOOL', 1],
                        'is_primary'    => ['BOOL', 0],
                        'visible'       => ['BOOL', 1],
                    ],
                    'PRIMARY_KEY' => 'currency_id',
                ],
                $this->table_prefix . 'oc_balance' => [
                    'COLUMNS' => [
                        'user_id'       => ['UINT', 0],
                        'currency_id'   => ['UINT', 0],
                        // SIGNED on purpose: debits/negative balances need it.
                        'balance'       => ['DECIMAL:10', '0.00'],
                    ],
                    'PRIMARY_KEY' => ['user_id', 'currency_id'],
                ],
                $this->table_prefix . 'oc_event' => [
                    'COLUMNS' => [
                        'event_id'      => ['UINT', null, 'auto_increment'],
                        'currency_id'   => ['UINT', 0],
                        // "trigger" is reserved in MySQL 8 — trigger_name instead.
                        'trigger_name'  => ['VCHAR:64', ''],
                        // SIGNED on purpose.
                        'amount'        => ['DECIMAL:10', '0.00'],
                        'forum_ids'     => ['TEXT', ''],
                        'max_per_day'   => ['UINT', 0],
                        'active'        => ['BOOL', 1],
                    ],
                    'PRIMARY_KEY' => 'event_id',
                    'KEYS' => [
                        'idx_trigger_name' => ['INDEX', ['trigger_name', 'active']],
                    ],
                ],
                $this->table_prefix . 'oc_transaction' => [
                    'COLUMNS' => [
                        'transaction_id'=> ['UINT', null, 'auto_increment'],
                        'user_id'       => ['UINT', 0],
                        'currency_id'   => ['UINT', 0],
                        // SIGNED on purpose: the ledger stores debit rows.
                        'amount'        => ['DECIMAL:10', '0.00'],
                        'trigger_name'  => ['VCHAR:64', ''],
                        'content_id'    => ['UINT', 0],
                        'note'          => ['VCHAR:255', ''],
                        'log_time'      => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'transaction_id',
                    'KEYS' => [
                        'idx_user_currency' => ['INDEX', ['user_id', 'currency_id']],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'oc_currency',
                $this->table_prefix . 'oc_balance',
                $this->table_prefix . 'oc_event',
                $this->table_prefix . 'oc_transaction',
            ],
        ];
    }
}
