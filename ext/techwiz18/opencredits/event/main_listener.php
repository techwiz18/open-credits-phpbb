<?php
/**
 * OpenCredits for phpBB — core event subscriptions.
 *
 * Listener only; all balance writes live in service/transact.php (Phase 2+).
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup' => 'load_language_on_setup',
            'core.permissions' => 'add_permissions',
        ];
    }

    public function load_language_on_setup($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = [
            'ext_name' => 'techwiz18/opencredits',
            'lang_set' => 'common',
        ];
        $event['lang_set_ext'] = $lang_set_ext;
    }

    public function add_permissions($event)
    {
        $permissions = $event['permissions'];
        $permissions['u_oc_view'] = ['lang' => 'ACL_U_OC_VIEW', 'cat' => 'misc'];
        $permissions['u_oc_transfer'] = ['lang' => 'ACL_U_OC_TRANSFER', 'cat' => 'misc'];
        $event['permissions'] = $permissions;
    }
}
