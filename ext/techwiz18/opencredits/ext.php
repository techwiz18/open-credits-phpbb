<?php
/**
 * OpenCredits for phpBB.
 *
 * @copyright (c) 2026 OpenCredits contributors
 * @license MIT
 */

namespace techwiz18\opencredits;

class ext extends \phpbb\extension\base
{
    /**
     * Enforce minimum phpBB + PHP versions (phpBB does not enforce
     * composer soft-require automatically in 3.3).
     */
    public function is_enableable()
    {
        return phpbb_version_compare(PHPBB_VERSION, '3.3.0', '>=')
            && phpbb_version_compare(PHP_VERSION, '8.1.0', '>=');
    }
}
