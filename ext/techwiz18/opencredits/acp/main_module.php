<?php
/**
 * OpenCredits for phpBB — ACP module: currency + trigger management.
 *
 * Metadata edits only (no balance writes): amounts apply to future awards.
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
        global $db, $request, $template, $language;

        switch ($mode)
        {
            case 'events':
                $this->page_title = $language->lang('ACP_OC_EVENTS');
                $this->handle_events($db, $request, $template, $language);
            break;

            case 'settings':
            default:
                $this->page_title = $language->lang('ACP_OC_SETTINGS');
                $this->handle_currencies($db, $request, $template, $language);
            break;
        }
    }

    /* ---------------- currencies ---------------- */

    protected function currency_table()
    {
        global $db, $table_prefix;
        return $table_prefix . 'oc_currency';
    }

    protected function handle_currencies($db, $request, $template, $language)
    {
        $table = $this->currency_table();
        $this->tpl_name = 'acp_oc_currencies';

        if ($request->is_set_post('oc_save'))
        {
            if (!check_form_key('oc_currencies'))
            {
                trigger_error('FORM_INVALID');
            }
            $this->save_currencies($request, $language);
            trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('oc_add'))
        {
            if (!check_form_key('oc_currencies'))
            {
                trigger_error('FORM_INVALID');
            }
            $title = trim($request->variable('oc_new_title', '', true));
            if ($title === '')
            {
                trigger_error($language->lang('OC_ACP_NEED_TITLE') . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $sql = 'INSERT INTO ' . $table . ' ' . $db->sql_build_array('INSERT', [
                'title'             => substr($title, 0, 255),
                'prefix'            => substr($request->variable('oc_new_prefix', '', true), 0, 16),
                'suffix'            => substr($request->variable('oc_new_suffix', '', true), 0, 16),
                'decimals'          => 2,
                'allow_negative'    => 0,
                'active'            => 1,
                'is_primary'        => 0,
                'visible'           => 1,
            ]);
            $db->sql_query($sql);
            trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
        }

        $result = $db->sql_query('SELECT * FROM ' . $table . ' ORDER BY currency_id ASC');
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('currencies', [
                'ID'              => (int) $row['currency_id'],
                'TITLE'           => $row['title'],
                'PREFIX'          => $row['prefix'],
                'SUFFIX'          => $row['suffix'],
                'ACTIVE'          => (int) $row['active'],
                'VISIBLE'         => (int) $row['visible'],
                'ALLOW_NEGATIVE'  => (int) $row['allow_negative'],
                'IS_PRIMARY'      => (int) $row['is_primary'],
            ]);
        }
        $db->sql_freeresult($result);

        add_form_key('oc_currencies');
        $template->assign_vars([
            'U_ACTION'  => $this->u_action,
        ]);
    }

    protected function save_currencies($request, $language)
    {
        global $db;
        $table = $this->currency_table();

        $rows = $request->variable('curr', [0 => ['title' => '', 'prefix' => '', 'suffix' => '', 'active' => 0, 'visible' => 0, 'allow_negative' => 0]], true);
        $primary_id = $request->variable('primary_id', 0);
        if (empty($rows) || $primary_id <= 0 || !isset($rows[$primary_id]))
        {
            trigger_error($language->lang('OC_ACP_NEED_PRIMARY') . adm_back_link($this->u_action), E_USER_WARNING);
        }

        foreach ($rows as $currency_id => $fields)
        {
            $currency_id = (int) $currency_id;
            if ($currency_id <= 0 || trim($fields['title']) === '')
            {
                trigger_error($language->lang('OC_ACP_NEED_TITLE') . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $sql = 'UPDATE ' . $table . ' SET ' . $db->sql_build_array('UPDATE', [
                'title'             => substr(trim($fields['title']), 0, 255),
                'prefix'            => substr($fields['prefix'], 0, 16),
                'suffix'            => substr($fields['suffix'], 0, 16),
                'active'            => !empty($fields['active']) ? 1 : 0,
                'visible'           => !empty($fields['visible']) ? 1 : 0,
                'allow_negative'    => !empty($fields['allow_negative']) ? 1 : 0,
                'is_primary'        => $currency_id === $primary_id ? 1 : 0,
            ]) . ' WHERE currency_id = ' . $currency_id;
            $db->sql_query($sql);
        }
    }

    /* ---------------- earn triggers ---------------- */

    protected function handle_events($db, $request, $template, $language)
    {
        $this->tpl_name = 'acp_oc_events';

        if ($request->is_set_post('oc_save'))
        {
            if (!check_form_key('oc_events'))
            {
                trigger_error('FORM_INVALID');
            }
            $this->save_events($request, $language);
            trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
        }

        $currencies = [];
        $result = $db->sql_query('SELECT currency_id, title FROM ' . $this->currency_table() . ' ORDER BY currency_id ASC');
        while ($row = $db->sql_fetchrow($result))
        {
            $currencies[(int) $row['currency_id']] = $row['title'];
        }
        $db->sql_freeresult($result);

        global $table_prefix;
        $result = $db->sql_query('SELECT * FROM ' . $table_prefix . 'oc_event ORDER BY event_id ASC');
        while ($row = $db->sql_fetchrow($result))
        {
            $template->assign_block_vars('events', [
                'ID'            => (int) $row['event_id'],
                'TRIGGER'       => $row['trigger_name'],
                'AMOUNT'        => $row['amount'],
                'MAX_PER_DAY'   => (int) $row['max_per_day'],
                'FORUM_IDS'     => $row['forum_ids'],
                'ACTIVE'        => (int) $row['active'],
            ]);
            // Nested per event row: a root-level block would not render here.
            foreach ($currencies as $currency_id => $title)
            {
                $template->assign_block_vars('events.currencies', [
                    'ID'        => $currency_id,
                    'TITLE'     => $title,
                    'SELECTED'  => $currency_id === (int) $row['currency_id'],
                ]);
            }
        }
        $db->sql_freeresult($result);

        add_form_key('oc_events');
        $template->assign_vars([
            'U_ACTION'  => $this->u_action,
        ]);
    }

    protected function save_events($request, $language)
    {
        global $db, $table_prefix;
        $table = $table_prefix . 'oc_event';

        $rows = $request->variable('ev', [0 => ['amount' => '', 'max_per_day' => 0, 'forum_ids' => '', 'active' => 0, 'currency_id' => 0]], true);
        foreach ($rows as $event_id => $fields)
        {
            $event_id = (int) $event_id;
            if ($event_id <= 0)
            {
                continue;
            }
            if (!is_numeric($fields['amount']))
            {
                trigger_error($language->lang('OC_ACP_BAD_AMOUNT') . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $forums = array_filter(array_map('trim', explode(',', $fields['forum_ids'])), function ($f) {
                return ctype_digit($f);
            });
            $sql = 'UPDATE ' . $table . ' SET ' . $db->sql_build_array('UPDATE', [
                'currency_id'   => max(0, (int) $fields['currency_id']),
                'amount'        => sprintf('%.2F', (float) $fields['amount']),
                'max_per_day'   => max(0, (int) $fields['max_per_day']),
                'forum_ids'     => implode(',', $forums),
                'active'        => !empty($fields['active']) ? 1 : 0,
            ]) . ' WHERE event_id = ' . $event_id;
            $db->sql_query($sql);
        }
    }
}
