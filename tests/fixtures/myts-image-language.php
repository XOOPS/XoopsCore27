<?php
/**
 * Loads the [img] text sanitizer extension with an incomplete language pack
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * Run as its own process by MytsImageLanguageFallbackTest: the test bootstrap
 * already defines the _MSC_ constants, so the missing ones cannot be staged there.
 * Argument 1 is "missing" (the pack has no misc.php) or "partial" (its misc.php
 * defines one of the three constants). Prints the three constants as JSON.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.5
 */

declare(strict_types=1);

define('XOOPS_ROOT_PATH', dirname(__DIR__, 2) . '/htdocs');

/**
 * Stands in for the core function with a language pack that is missing or incomplete
 *
 * @param string $name language file name
 *
 * @return bool
 */
function xoops_loadLanguage($name)
{
    if ('partial' === ($GLOBALS['argv'][1] ?? '')) {
        define('_MSC_RESIZED_IMAGE', 'Verkleinertes Bild');

        return true;
    }

    return false;
}

class MyTextSanitizerExtension {}

require XOOPS_ROOT_PATH . '/class/textsanitizer/image/image.php';

$constants = [];
foreach (['_MSC_RESIZED_IMAGE', '_MSC_CLICK_TO_OPEN_IMAGE', '_MSC_ORIGINAL_IMAGE'] as $name) {
    $constants[$name] = defined($name) ? constant($name) : null;
}
echo json_encode($constants);
