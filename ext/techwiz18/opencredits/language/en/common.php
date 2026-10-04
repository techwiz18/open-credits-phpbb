<?php
/**
 * OpenCredits for phpBB — front-end language strings.
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
    'OC_TITLE'          => 'OpenCredits',
    'OC_WALLET'         => 'Wallet',
    'OC_BALANCE'        => 'Balance',
]);
