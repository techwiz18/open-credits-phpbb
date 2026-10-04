<?php
/**
 * OpenCredits for phpBB — permission language strings (auto-loaded in ACP).
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
    'ACL_U_OC_VIEW'     => 'Can view credit wallet',
    'ACL_U_OC_TRANSFER' => 'Can transfer credits',
]);
