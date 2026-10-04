<?php
/**
 * OpenCredits for phpBB — UCP module info.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\ucp;

class main_info
{
    public function module()
    {
        return [
            'filename'  => '\techwiz18\opencredits\ucp\main_module',
            'title'     => 'UCP_OC_WALLET',
            'modes'     => [
                'history'   => [
                    'title' => 'UCP_OC_HISTORY',
                    'auth'  => 'acl_u_oc_view',
                    'cat'   => ['UCP_OC_WALLET'],
                ],
                'transfer'  => [
                    'title' => 'UCP_OC_TRANSFER',
                    'auth'  => 'acl_u_oc_transfer',
                    'cat'   => ['UCP_OC_WALLET'],
                ],
            ],
        ];
    }
}
