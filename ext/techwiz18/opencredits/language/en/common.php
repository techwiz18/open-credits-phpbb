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
    'OC_WALLET_CURRENCY' => 'Currency',
    'OC_WALLET_BALANCE'  => 'Balance',

    'UCP_OC_WALLET'     => 'OpenCredits',
    'UCP_OC_HISTORY'    => 'Transaction history',
    'UCP_OC_TRANSFER'   => 'Transfer credits',

    'OC_HISTORY_EMPTY'  => 'No transactions yet.',
    'OC_HISTORY_AMOUNT' => 'Amount',
    'OC_HISTORY_WHAT'   => 'Event',
    'OC_HISTORY_NOTE'   => 'Note',
    'OC_HISTORY_WHEN'   => 'Date',

    'OC_TRANSFER_TO'        => 'Recipient username',
    'OC_TRANSFER_AMOUNT'    => 'Amount',
    'OC_TRANSFER_CURRENCY'  => 'Currency',
    'OC_TRANSFER_SUBMIT'    => 'Send credits',
    'OC_TRANSFER_SUCCESS'   => 'Credits transferred.',
    'OC_TRANSFER_FUNDS'     => 'Transfer refused: insufficient funds.',
    'OC_TRANSFER_BAD_USER'  => 'Recipient not found (or cannot receive credits).',
    'OC_TRANSFER_SELF'      => 'You cannot transfer credits to yourself.',
    'OC_TRANSFER_BAD_AMOUNT'    => 'Enter an amount greater than zero (max 2 decimals).',
    'OC_TRANSFER_BAD_CURRENCY'  => 'Pick a valid currency.',
    'OC_DONATE'                 => 'Donate',
]);