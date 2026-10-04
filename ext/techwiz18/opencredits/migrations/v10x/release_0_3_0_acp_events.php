<?php
/**
 * OpenCredits for phpBB — v0.3.0: earn-triggers ACP mode.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\migrations\v10x;

class release_0_3_0_acp_events extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['techwiz18_opencredits_version'])
            && version_compare($this->config['techwiz18_opencredits_version'], '0.3.0', '>=');
    }

    public static function depends_on()
    {
        return ['\techwiz18\opencredits\migrations\v10x\release_0_2_2_ucp_tab'];
    }

    public function update_data()
    {
        return [
            ['config.update', ['techwiz18_opencredits_version', '0.3.0']],

            ['module.add', [
                'acp',
                'ACP_OC_TITLE',
                [
                    'module_basename'   => '\techwiz18\opencredits\acp\main_module',
                    'module_mode'       => 'events',
                    'module_langname'   => 'ACP_OC_EVENTS',
                    'module_auth'       => 'acl_a_board',
                ],
            ]],
        ];
    }
}
