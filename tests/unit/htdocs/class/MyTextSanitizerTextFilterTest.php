<?php
/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

declare(strict_types=1);

/**
 * MyTextSanitizer::textFilter() without Frameworks/purifier.
 *
 * The strong path loads Frameworks/purifier, which the core package does not
 * ship, so every site ran the pattern fallback, which removes selected tags
 * and schemes but keeps event-handler attributes. The bundled HTMLPurifier
 * now filters instead.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';

#[CoversClass(MyTextSanitizer::class)]
final class MyTextSanitizerTextFilterTest extends TestCase
{
    #[Test]
    public function theFilterRemovesEventHandlersWithoutTheFrameworkPurifier(): void
    {
        self::assertTrue(class_exists('HTMLPurifier'), 'the bundled HTMLPurifier is autoloadable');

        $myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $myts->path_basic  = XOOPS_ROOT_PATH . '/class/textsanitizer';
        $myts->path_config = XOOPS_VAR_PATH . '/configs/textsanitizer';
        $myts->path_plugin = XOOPS_ROOT_PATH . '/Frameworks/textsanitizer';
        $myts->config      = ['extensions' => []];

        $out = (string) $myts->textFilter('<p>ok</p><img src="x.png" onerror="alert(1)"><a href="javascript:alert(1)">x</a>', true);

        self::assertStringContainsString('<p>ok</p>', $out);
        self::assertStringNotContainsStringIgnoringCase('onerror', $out);
        self::assertStringNotContainsStringIgnoringCase('javascript:', $out);
    }
}
