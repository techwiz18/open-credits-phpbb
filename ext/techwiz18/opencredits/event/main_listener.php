<?php
/**
 * OpenCredits for phpBB — core event subscriptions.
 *
 * Thin glue only: every earning path delegates to the Transact service,
 * which is the single place that writes balances.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
    /** @var \techwiz18\opencredits\service\transact */
    protected $transact;

    /** @var \phpbb\auth\auth */
    protected $auth;

    /** @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\user */
    protected $user;

    /** @var string */
    protected $root_path;

    /** @var string */
    protected $php_ext;

    /** @var array|null Per-request cache of active currency rows */
    protected $currency_cache = null;

    public function __construct(\techwiz18\opencredits\service\transact $transact, \phpbb\auth\auth $auth, \phpbb\template\template $template, \phpbb\user $user, $root_path, $php_ext)
    {
        $this->transact = $transact;
        $this->auth = $auth;
        $this->template = $template;
        $this->user = $user;
        $this->root_path = $root_path;
        $this->php_ext = $php_ext;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup'       => [
                ['load_language_on_setup'],
                ['award_daily_login'],
            ],
            'core.permissions'      => 'add_permissions',
            'core.submit_post_end'  => 'on_submit_post',
            'core.user_add_after'   => 'on_user_register',
            'core.viewtopic_modify_post_row'                    => 'show_postbit_credits',
            'core.memberlist_modify_view_profile_template_vars' => 'show_profile_credits',
            'core.ucp_display_module_before'                    => 'show_front_summary',
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

    /**
     * New topic → `thread` trigger; reply/quote → `post` trigger.
     * Edits, unapproved posts, and guest posts never award.
     */
    public function on_submit_post($event)
    {
        $mode = $event['mode'];
        if ($mode !== 'post' && $mode !== 'reply' && $mode !== 'quote')
        {
            return;
        }

        $data = $event['data'];
        $visibility = isset($event['post_visibility']) ? (int) $event['post_visibility'] : (int) ($data['post_visibility'] ?? 0);
        if ($visibility !== ITEM_APPROVED)
        {
            return;
        }

        $poster_id = (int) ($data['poster_id'] ?? 0);
        if ($poster_id <= 0 || $poster_id === (int) ANONYMOUS)
        {
            return;
        }

        if ($mode === 'post')
        {
            $this->transact->award_by_trigger('thread', $poster_id, (int) ($data['topic_id'] ?? 0), (int) ($data['forum_id'] ?? 0), 'New thread');
            return;
        }
        $this->transact->award_by_trigger('post', $poster_id, (int) ($data['post_id'] ?? 0), (int) ($data['forum_id'] ?? 0), 'New reply');
    }

    /**
     * New registration → `register` trigger.
     */
    public function on_user_register($event)
    {
        $user_id = (int) $event['user_id'];
        if ($user_id <= 0)
        {
            return;
        }
        $this->transact->award_by_trigger('register', $user_id, 0, 0, 'Welcome bonus');
    }

    /**
     * Daily visit → `daily_login` trigger, exactly once per day.
     * Registration day is skipped (the register bonus already paid).
     */
    public function award_daily_login($event)
    {
        $user_data = $event['user_data'];
        if (empty($user_data['is_registered']))
        {
            return;
        }
        $user_id = (int) ($user_data['user_id'] ?? 0);
        if ($user_id <= 0 || $user_id === (int) ANONYMOUS)
        {
            return;
        }
        if ((int) ($user_data['user_type'] ?? 0) === (int) USER_IGNORE)
        {
            return;
        }

        $today = $this->transact->day_start();
        if ((int) ($user_data['user_regdate'] ?? 0) >= $today)
        {
            return;
        }
        if ($this->transact->has_award_today($user_id, 'daily_login', $today))
        {
            return;
        }
        $this->transact->award_by_trigger('daily_login', $user_id, 0, 0, 'Daily visit');
    }

    /**
     * Postbit shows the primary currency (active only), even at zero —
     * switching primary visibly changes the postbit. Values ride on
     * post_row (per-post) because root template blocks would repeat
     * under every post on the page. Gated on u_oc_view.
     */
    public function show_postbit_credits($event)
    {
        if (!$this->auth->acl_get('u_oc_view'))
        {
            return;
        }
        $poster_id = (int) $event['poster_id'];
        if ($poster_id <= 0 || $poster_id === (int) ANONYMOUS)
        {
            return;
        }
        if ($this->currency_cache === null)
        {
            $this->currency_cache = $this->transact->active_currencies();
        }
        $primary = null;
        foreach ($this->currency_cache as $currency)
        {
            if ((int) $currency['is_primary'])
            {
                $primary = $currency;
                break;
            }
        }
        if ($primary === null)
        {
            return;
        }
        $balance = $this->transact->get_balance($poster_id, (int) $primary['currency_id']);
        $post_row = $event['post_row'];
        $post_row['OC_CREDITS_TITLE'] = $primary['title'];
        $post_row['OC_CREDITS_AMOUNTS'] = $primary['prefix'] . $balance . $primary['suffix'];
        if ($this->auth->acl_get('u_oc_transfer') && $poster_id !== (int) ($this->user->data['user_id'] ?? 0))
        {
            $post_row['U_OC_DONATE'] = $this->donate_url($poster_id);
        }
        $event['post_row'] = $post_row;
    }

    /**
     * Profile lists every active, visible currency — including zero
     * balances, so new currencies appear without needing earn triggers.
     * Gated on u_oc_view for the viewing user.
     */
    public function show_profile_credits($event)
    {
        if (!$this->auth->acl_get('u_oc_view'))
        {
            return;
        }
        $user_id = (int) $event['user_id'];
        if ($user_id <= 0)
        {
            return;
        }
        if ($this->currency_cache === null)
        {
            $this->currency_cache = $this->transact->active_currencies();
        }
        foreach ($this->currency_cache as $currency)
        {
            if (!(int) $currency['visible'])
            {
                continue;
            }
            $balance = $this->transact->get_balance($user_id, (int) $currency['currency_id']);
            $this->template->assign_block_vars('oc_wallet', [
                'TITLE'     => $currency['title'],
                'BALANCE'   => $currency['prefix'] . $balance . $currency['suffix'],
            ]);
        }
        if ($this->auth->acl_get('u_oc_transfer') && $user_id !== (int) ($this->user->data['user_id'] ?? 0))
        {
            $this->template->assign_var('U_OC_DONATE', $this->donate_url($user_id));
        }
    }

    /**
     * UCP transfer form pre-filled with this recipient. Mirrors
     * p_master::get_module_identifier() (backslashes become dashes).
     */
    protected function donate_url($user_id)
    {
        $identifier = str_replace('\\', '-', '\techwiz18\opencredits\ucp\main_module');
        return append_sid($this->root_path . 'ucp.' . $this->php_ext, 'i=' . $identifier . '&mode=transfer&oc_to=' . (int) $user_id);
    }

    /**
     * Wallet summary on the UCP front page ("Your activity" section).
     * Lists every active, visible currency, including zero balances.
     * Gated on u_oc_view.
     */
    public function show_front_summary($event)
    {
        // The event fires before set_active() resolves defaults, and the id
        // arrives in short ('main'), full ('ucp_main'), or empty form
        // depending on how the front page was reached.
        $id = (string) $event['id'];
        $mode = (string) $event['mode'];
        $is_front = ($id === '' || $id === 'main' || $id === 'ucp_main') && ($mode === '' || $mode === 'front');
        if (!$is_front)
        {
            return;
        }
        if (!$this->auth->acl_get('u_oc_view'))
        {
            return;
        }
        $user_id = (int) ($this->user->data['user_id'] ?? 0);
        if ($user_id <= 0 || $user_id === (int) ANONYMOUS)
        {
            return;
        }
        if ($this->currency_cache === null)
        {
            $this->currency_cache = $this->transact->active_currencies();
        }
        foreach ($this->currency_cache as $currency)
        {
            if (!(int) $currency['visible'])
            {
                continue;
            }
            $balance = $this->transact->get_balance($user_id, (int) $currency['currency_id']);
            $this->template->assign_block_vars('oc_front', [
                'TITLE'     => $currency['title'],
                'BALANCE'   => $currency['prefix'] . $balance . $currency['suffix'],
            ]);
        }
    }
}
