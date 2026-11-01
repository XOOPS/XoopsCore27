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

    #[Test]
    public function theUrlCheckRemovesWhatBrowsersRemoveBeforeReadingTheScheme(): void
    {
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/menus/main.php');
        $start  = strpos($source, 'function menus_sanitize_url(');
        self::assertNotFalse($start);
        $body   = substr($source, $start, (int) strpos($source, "\n}\n", $start) - $start);
        $strip  = strpos($body, 'str_replace(["\t", "\n", "\r"], \'\', $url)');
        $scheme = strpos($body, "preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*):#'");
        self::assertNotFalse($strip, 'tabs and line breaks are removed');
        self::assertNotFalse($scheme);
        self::assertLessThan($scheme, $strip, 'before the scheme is read');
    }
}
