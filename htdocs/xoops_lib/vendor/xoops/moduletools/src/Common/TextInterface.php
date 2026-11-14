<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/**
 * @category     Module
 * @package      mtools
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @copyright    https://xoops.org 2000-2026 &copy; XOOPS Project
 * @author       Mamba <mambax7@gmail.com>
 */

/**
 * Contract for the pure text helpers extracted from SysUtility.
 *
 * Implementations MUST be side-effect free: no request reads, no output, no globals,
 * no database. This interface freezes the signature so the class can graduate to XMF
 * unchanged (the implementation and this interface move together with its golden test).
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
interface TextInterface
{
    /**
     * Truncate a string to a number of characters while preserving whole words and HTML tags.
     *
     * @param string $text         String to truncate.
     * @param int    $length       Length of returned string, including ellipsis.
     * @param string $ending       Ending to be appended to the trimmed string.
     * @param bool   $exact        If false, $text will not be cut mid-word.
     * @param bool   $considerHtml If true, HTML tags are handled correctly.
     *
     * @return string Trimmed string.
     */
    public static function truncateHtml(
        string $text,
        int $length = 100,
        string $ending = '...',
        bool $exact = false,
        bool $considerHtml = true
    ): string;
}
