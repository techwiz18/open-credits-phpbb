<?php
/**
 * OpenCredits for phpBB — v0.2.0: UCP wallet module.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\migrations\v10x;

class release_0_2_0_ucp extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['techwiz18_opencredits_version'])
            && version_compare($this->config['techwiz18_opencredits_version'], '0.2.0', '>=');
    }

    public static function depends_on()
    {
        return ['\techwiz18\opencredits\migrations\v10x\release_0_1_0_data'];
    }

    public function update_data()
    {
        return [
            ['config.update', ['techwiz18_opencredits_version', '0.2.0']],

            ['module.add', ['ucp', 'UCP_MAIN', 'UCP_OC_WALLET']],
            ['module.add', [
                'ucp',
                'UCP_OC_WALLET',
                [
                    'module_basename'   => '\techwiz18\opencredits\ucp\main_module',
                    'modes'             => ['history', 'transfer'],
                ],
            ]],
        ];
    }
}
