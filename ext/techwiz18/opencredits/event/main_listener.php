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

    /** @var array Per-request cache: user_id => [currency_id => balance] */
    protected $balance_cache = [];

    /** @var array|null Per-request cache of active currency rows */
    protected $currency_cache = null;

    public function __construct(\techwiz18\opencredits\service\transact $transact, \phpbb\auth\auth $auth, \phpbb\template\template $template)
    {
        $this->transact = $transact;
        $this->auth = $auth;
        $this->template = $template;
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
     * Postbit balance line (visible currencies only). Gated on u_oc_view
     * for the viewing user.
     */
    public function show_postbit_credits($event)
    {
        if (!$this->auth->acl_get('u_oc_view'))
        {
            return;
        }
        $label = $this->credits_label((int) $event['poster_id'], true);
        if ($label === '')
        {
            return;
        }
        $post_row = $event['post_row'];
        $post_row['OC_CREDITS'] = $label;
        $event['post_row'] = $post_row;
    }

    /**
     * Profile wallet rows, one per held currency ("Credits: $6.00").
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
            $balance = $this->transact->get_balance($user_id, (int) $currency['currency_id']);
            if ($balance === '0.00')
            {
                continue;
            }
            $this->template->assign_block_vars('oc_wallet', [
                'TITLE'     => $currency['title'],
                'BALANCE'   => $currency['prefix'] . $balance . $currency['suffix'],
            ]);
        }
    }

    /**
     * "Credits: $6.00" style label per currency. Returns '' when the user
     * holds nothing (keeps postbit clean). Identical string on postbit and
     * profile so the two surfaces never disagree.
     */
    protected function credits_label($user_id, $visible_only)
    {
        if ($user_id <= 0)
        {
            return '';
        }
        if ($this->currency_cache === null)
        {
            $this->currency_cache = $this->transact->active_currencies();
        }
        if (!isset($this->balance_cache[$user_id]))
        {
            $this->balance_cache[$user_id] = [];
            foreach ($this->currency_cache as $currency)
            {
                $balance = $this->transact->get_balance($user_id, (int) $currency['currency_id']);
                if ($balance !== '0.00')
                {
                    $this->balance_cache[$user_id][(int) $currency['currency_id']] = $balance;
                }
            }
        }
        $parts = [];
        foreach ($this->currency_cache as $currency)
        {
            $currency_id = (int) $currency['currency_id'];
            if ($visible_only && !(int) $currency['visible'])
            {
                continue;
            }
            if (!isset($this->balance_cache[$user_id][$currency_id]))
            {
                continue;
            }
            $parts[] = $currency['title'] . ': ' . $currency['prefix'] . $this->balance_cache[$user_id][$currency_id] . $currency['suffix'];
        }
        return implode(', ', $parts);
    }
}
