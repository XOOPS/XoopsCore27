<?php

declare(strict_types=1);

/**
 * xoops_getcss() for the logger popup test: include/functions.php cannot be
 * loaded beside tests/bootstrap.php (both declare xoops_getHandler()).
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

if (!function_exists('xoops_getcss')) {
    function xoops_getcss($theme = ''): string
    {
        return '';
    }
}
