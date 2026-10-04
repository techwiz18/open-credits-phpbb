<?php
/**
 * OpenCredits for phpBB — ACP module info.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\acp;

class main_info
{
    public function module()
    {
        return [
            'filename'  => '\techwiz18\opencredits\acp\main_module',
            'title'     => 'ACP_OC_TITLE',
            'modes'     => [
                'settings'  => [
                    'title' => 'ACP_OC_SETTINGS',
                    'auth'  => 'acl_a_board',
                    'cat'   => ['ACP_OC_TITLE'],
                ],
                'events'    => [
                    'title' => 'ACP_OC_EVENTS',
                    'auth'  => 'acl_a_board',
                    'cat'   => ['ACP_OC_TITLE'],
                ],
                'tools'     => [
                    'title' => 'ACP_OC_TOOLS',
                    'auth'  => 'acl_a_board',
                    'cat'   => ['ACP_OC_TITLE'],
                ],
            ],
        ];
    }
}
