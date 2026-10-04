<?php
/**
 * OpenCredits for phpBB — v0.1.0 data: seed currency + events, ACP module, permissions.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\migrations\v10x;

class release_0_1_0_data extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['techwiz18_opencredits_version'])
            && version_compare($this->config['techwiz18_opencredits_version'], '0.1.0', '>=');
    }

    public static function depends_on()
    {
        return ['\techwiz18\opencredits\migrations\v10x\release_0_1_0_schema'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['techwiz18_opencredits_version', '0.1.0']],

            ['permission.add', ['u_oc_view', true]],
            ['permission.add', ['u_oc_transfer', true]],
            ['permission.permission_set', ['REGISTERED', 'u_oc_view', 'group', true]],
            ['permission.permission_set', ['REGISTERED', 'u_oc_transfer', 'group', true]],

            ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_OC_TITLE']],
            ['module.add', [
                'acp',
                'ACP_OC_TITLE',
                [
                    'module_basename'   => '\techwiz18\opencredits\acp\main_module',
                    'modes'             => ['settings'],
                ],
            ]],

            ['custom', [[$this, 'seed_default_data']]],
        ];
    }

    public function seed_default_data()
    {
        $currency_table = $this->table_prefix . 'oc_currency';
        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $currency_table;
        $result = $this->db->sql_query($sql);
        $count = (int) $this->db->sql_fetchfield('cnt');
        $this->db->sql_freeresult($result);

        if ($count === 0)
        {
            $sql = 'INSERT INTO ' . $currency_table . ' ' . $this->db->sql_build_array('INSERT', [
                'title'             => 'Credits',
                'prefix'            => '$',
                'suffix'            => '',
                'decimals'          => 2,
                'allow_negative'    => 1,
                'active'            => 1,
                'is_primary'        => 1,
                'visible'           => 1,
            ]);
            $this->db->sql_query($sql);
            $currency_id = (int) $this->db->sql_nextid();

            $seeds = [
                ['thread', '5.00'],
                ['post', '1.00'],
                ['register', '10.00'],
                ['daily_login', '5.00'],
            ];
            foreach ($seeds as [$trigger, $amount])
            {
                $sql = 'INSERT INTO ' . $this->table_prefix . 'oc_event ' . $this->db->sql_build_array('INSERT', [
                    'currency_id'   => $currency_id,
                    'trigger'       => $trigger,
                    'amount'        => $amount,
                    'forum_ids'     => '',
                    'max_per_day'   => 0,
                    'active'        => 1,
                ]);
                $this->db->sql_query($sql);
            }
        }
    }
}
