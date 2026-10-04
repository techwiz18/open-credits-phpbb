<?php
/**
 * OpenCredits for phpBB — ACP module language strings (auto-loaded).
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = [];
}

$lang = array_merge($lang, [
    'ACP_OC_TITLE'      => 'OpenCredits',
    'ACP_OC_SETTINGS'   => 'Currencies',
    'ACP_OC_EVENTS'     => 'Earn triggers',

    'ACP_OC_CURRENCIES_EXPLAIN' => 'Active is the master switch (inactive currencies earn and show nothing). Primary is the postbit currency — exactly one is required. Visible gates the profile and front-page lists. New currencies appear everywhere once active; they only earn once trigger rows point at them.',
    'ACP_OC_EVENTS_EXPLAIN'     => 'Amounts pay out on every matching action while active. Max per day 0 means unlimited. Forum IDs is an optional comma-separated list (empty means all forums).',

    'OC_COL_TITLE'      => 'Title',
    'OC_COL_PREFIX'     => 'Prefix',
    'OC_COL_SUFFIX'     => 'Suffix',
    'OC_COL_ACTIVE'     => 'Active',
    'OC_COL_VISIBLE'    => 'Visible',
    'OC_COL_NEGATIVE'   => 'Allow negative',
    'OC_COL_PRIMARY'    => 'Primary',
    'OC_COL_ADD_CURRENCY' => 'Add currency',
    'OC_COL_ADD'        => 'Add',
    'OC_COL_TRIGGER'    => 'Trigger',
    'OC_COL_CURRENCY'   => 'Currency',
    'OC_COL_AMOUNT'     => 'Amount',
    'OC_COL_MAXDAY'     => 'Max per day',
    'OC_COL_FORUMS'     => 'Forum IDs',

    'OC_ACP_NEED_TITLE'     => 'Every currency needs a title.',
    'OC_ACP_NEED_PRIMARY'   => 'Exactly one currency must be primary.',
    'OC_ACP_BAD_AMOUNT'     => 'Amounts must be numbers.',
]);
