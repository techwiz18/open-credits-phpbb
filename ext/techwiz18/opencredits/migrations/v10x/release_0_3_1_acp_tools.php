<?php
/**
 * OpenCredits for phpBB — v0.3.1: Tools ACP mode (rebuild).
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\migrations\v10x;

class release_0_3_1_acp_tools extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['techwiz18_opencredits_version'])
            && version_compare($this->config['techwiz18_opencredits_version'], '0.3.1', '>=');
    }

    public static function depends_on()
    {
        return ['\techwiz18\opencredits\migrations\v10x\release_0_3_0_acp_events'];
    }

    public function update_data()
    {
        return [
            ['config.update', ['techwiz18_opencredits_version', '0.3.1']],

            ['module.add', [
                'acp',
                'ACP_OC_TITLE',
                [
                    'module_basename'   => '\techwiz18\opencredits\acp\main_module',
                    'module_mode'       => 'tools',
                    'module_langname'   => 'ACP_OC_TOOLS',
                    'module_auth'       => 'acl_a_board',
                ],
            ]],
        ];
    }
}
