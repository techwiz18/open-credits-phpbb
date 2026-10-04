<?php
/**
 * OpenCredits for phpBB — ACP module (Phase 1 stub; management UI lands in Phase 2).
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\acp;

class main_module
{
    public $page_title;
    public $tpl_name;
    public $u_action;

    public function main($id, $mode)
    {
        global $template, $language;

        $this->page_title = $language->lang('ACP_OC_SETTINGS');
        $this->tpl_name = 'acp_oc_settings';

        $template->assign_vars([
            'OC_SKELETON_NOTICE' => $language->lang('ACP_OC_SKELETON'),
            'U_ACTION'           => $this->u_action,
        ]);
    }
}
