<?php
/**
 * OpenCredits for phpBB — v0.2.1: flatten UCP modes under UCP_MAIN.
 *
 * The 0.2.0 layout nested modes two deep (UCP_MAIN → category → modes) but
 * the UCP sidebar only renders depth-1 items, so Transfer never appeared.
 * Core puts mode rows directly under the tab — we do the same now.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\migrations\v10x;

class release_0_2_1_ucp_flat extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['techwiz18_opencredits_version'])
            && version_compare($this->config['techwiz18_opencredits_version'], '0.2.1', '>=');
    }

    public static function depends_on()
    {
        return ['\techwiz18\opencredits\migrations\v10x\release_0_2_0_ucp'];
    }

    public function update_data()
    {
        return [
            ['config.update', ['techwiz18_opencredits_version', '0.2.1']],

            ['module.remove', ['ucp', 'UCP_OC_WALLET', 'UCP_OC_TRANSFER']],
            ['module.remove', ['ucp', 'UCP_OC_WALLET', 'UCP_OC_HISTORY']],
            ['module.remove', ['ucp', 'UCP_MAIN', 'UCP_OC_WALLET']],

            ['module.add', ['ucp', 'UCP_MAIN', [
                'module_basename'   => '\techwiz18\opencredits\ucp\main_module',
                'module_langname'   => 'UCP_OC_HISTORY',
                'module_mode'       => 'history',
                'module_auth'       => 'acl_u_oc_view',
            ]]],
            ['module.add', ['ucp', 'UCP_MAIN', [
                'module_basename'   => '\techwiz18\opencredits\ucp\main_module',
                'module_langname'   => 'UCP_OC_TRANSFER',
                'module_mode'       => 'transfer',
                'module_auth'       => 'acl_u_oc_transfer',
            ]]],
        ];
    }
}
