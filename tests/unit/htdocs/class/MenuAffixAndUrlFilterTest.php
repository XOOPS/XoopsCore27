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
 * Menu prefixes/suffixes and menu URLs.
 *
 * The affix filter removed attributes with regular expressions that missed
 * separators browsers accept (<span/onmouseover=...>), and the menu
 * administration list printed affixes with no filter at all. Both now go
 * through one parser-based allowlist. The URL check missed schemes split by
 * a tab or line break, which browsers remove ("java<TAB>script:").
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MenuAffixAndUrlFilterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/theme.php';
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostile(): array
    {
        return [
            'slash separator'   => ['<span/onmouseover=alert(1)>x</span>'],
            'quoted handler'    => ['<i class="fa" onclick="alert(1)"></i>'],
            'newline separator' => ["<b\nonmouseover=alert(1)>x</b>"],
            'style'             => ['<span style="background:url(javascript:alert(1))">x</span>'],
            'script'            => ['<script>alert(1)</script>'],
            'image'             => ['<img src=x onerror=alert(1)>'],
            'link'              => ['<a href="javascript:alert(1)">x</a>'],
        ];
    }

    #[Test]
    #[DataProvider('hostile')]
    public function anAffixKeepsNoHandlerScriptOrStyle(string $affix): void
    {
        $out = xos_opal_Theme::filterMenuAffix($affix);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=|<script|<img|<a\s|style\s*=|javascript:/i', $out, $out);
    }

    #[Test]
    public function iconsAndEmphasisSurvive(): void
    {
        self::assertSame(
            '<i class="fa fa-home"></i> <strong>New</strong> <span class="badge">3</span><small>s</small>',
            xos_opal_Theme::filterMenuAffix('<i class="fa fa-home"></i> <strong>New</strong> <span class="badge">3</span><small>s</small>')
        );
        self::assertSame('', xos_opal_Theme::filterMenuAffix(''));
    }

    #[Test]
    public function theAdministrationListFiltersEveryAffix(): void
    {
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/menus/main.php');
        foreach (["\$item->getVar('items_prefix', 'n')", "\$item->getVar('items_suffix', 'n')",
                  "\$cat->getVar('category_prefix', 'n')", "\$cat->getVar('category_suffix', 'n')"] as $read) {
            $total    = substr_count($source, $read);
            $filtered = substr_count($source, '\xos_opal_Theme::filterMenuAffix((string) ' . $read . ')');
            self::assertGreaterThan(0, $total, $read);
            self::assertSame($total, $filtered, $read . ' reaches the list unfiltered');
        }
    }

    /**
     * @return array<string, array{string, string}> input => expected
     */
    public static function menuUrls(): array
    {
        return [
            'tab inside the scheme'      => ["java\tscript:alert(1)", ''],
            'newline inside the scheme'  => ["java\nscript:alert(1)", ''],
            'entity-encoded tab'         => ['java&#9;script:alert(1)', ''],
            'entity-encoded letter'      => ['&#106;avascript:alert(1)', ''],
            'leading control and space'  => ["\x01 javascript:alert(1)", ''],
            'upper case'                 => ['JaVaScRiPt:alert(1)', ''],
            'data scheme'                => ['data:text/html,x', ''],
            'percent-encoded is a path'  => ['java%0Ascript:alert(1)', 'java%0Ascript:alert(1)'],
            'https'                      => ['https://xoops.org/', 'https://xoops.org/'],
            'mailto'                     => ['mailto:a@b.test', 'mailto:a@b.test'],
            'site-relative'              => ['/modules/news/', '/modules/news/'],
            'query only'                 => ['?op=x', '?op=x'],
            'empty'                      => ['', ''],
            'fragment'                   => ['#', '#'],
        ];
    }

    /**
     * The function is loaded from the admin controller, as written, into a
     * test namespace (the controller itself cannot be included).
     */
    #[Test]
    #[DataProvider('menuUrls')]
    public function theUrlCheckRejectsObfuscatedSchemesAndKeepsSafeUrls(string $url, string $expected): void
    {
        $fn = 'MenuUrlSandbox\\menus_sanitize_url';
        if (!function_exists($fn)) {
            $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/menus/main.php');
            $start  = strpos($source, 'function menus_sanitize_url(');
            self::assertNotFalse($start);
            $body = substr($source, $start, (int) strpos($source, "\n}\n", $start) - $start + 2);
            eval('namespace MenuUrlSandbox; ' . $body);
        }

        self::assertSame($expected, $fn($url));
    }
}
