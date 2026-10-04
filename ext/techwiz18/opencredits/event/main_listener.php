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

    public function __construct(\techwiz18\opencredits\service\transact $transact)
    {
        $this->transact = $transact;
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
            $this->transact->award_by_trigger('thread', $poster_id, (int) ($data['topic_id'] ?? 0), (int) ($data['forum_id'] ?? 0));
            return;
        }
        $this->transact->award_by_trigger('post', $poster_id, (int) ($data['post_id'] ?? 0), (int) ($data['forum_id'] ?? 0));
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
        $this->transact->award_by_trigger('register', $user_id);
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
        $this->transact->award_by_trigger('daily_login', $user_id);
    }
}
