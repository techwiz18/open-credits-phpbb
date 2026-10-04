<?php
/**
 * OpenCredits for phpBB — v0.2.2: own UCP tab.
 *
 * Modes directly under UCP_MAIN crowded the Overview sidebar, so the wallet
 * gets a top-level tab (parent 0, like core's UCP_MAIN/UCP_PROFILE) with both
 * modes beneath it.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\migrations\v10x;

class release_0_2_2_ucp_tab extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['techwiz18_opencredits_version'])
            && version_compare($this->config['techwiz18_opencredits_version'], '0.2.2', '>=');
    }

    public static function depends_on()
    {
        return ['\techwiz18\opencredits\migrations\v10x\release_0_2_1_ucp_flat'];
    }

    public function update_data()
    {
        return [
            ['config.update', ['techwiz18_opencredits_version', '0.2.2']],

            ['module.remove', ['ucp', 'UCP_MAIN', 'UCP_OC_HISTORY']],
            ['module.remove', ['ucp', 'UCP_MAIN', 'UCP_OC_TRANSFER']],

            ['module.add', ['ucp', 0, 'UCP_OC_WALLET']],
            ['module.add', ['ucp', 'UCP_OC_WALLET', [
                'module_basename'   => '\techwiz18\opencredits\ucp\main_module',
                'module_langname'   => 'UCP_OC_HISTORY',
                'module_mode'       => 'history',
                'module_auth'       => 'acl_u_oc_view',
            ]]],
            ['module.add', ['ucp', 'UCP_OC_WALLET', [
                'module_basename'   => '\techwiz18\opencredits\ucp\main_module',
                'module_langname'   => 'UCP_OC_TRANSFER',
                'module_mode'       => 'transfer',
                'module_auth'       => 'acl_u_oc_transfer',
            ]]],
        ];
    }
}
