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
 * The administration redirect message (jGrowl).
 *
 * SystemCorePreload pasted the session's redirect message between double
 * quotes in an inline script, so a quote, a backslash escape or "</script>"
 * in it became code. The permission saver put posted permission and item
 * names into that message without escaping them.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

namespace modulessystem;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class RedirectMessageEncodingTest extends TestCase
{
    private mixed $theme = null;

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/modules/system/preloads/core.php';
        $this->theme = $GLOBALS['xoTheme'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['xoTheme'] = $this->theme;
        unset($_SESSION['redirect_message']);
    }

    private function render(string $message): string
    {
        $collector = new class {
            public string $script = '';

            public function addStylesheet(...$args): void {}

            public function addScript($src = '', $attributes = [], $content = ''): void
            {
                $this->script .= $content;
            }
        };
        $GLOBALS['xoTheme']          = $collector;
        $_SESSION['redirect_message'] = $message;
        \SystemCorePreload::eventSystemClassGuiHeader([]);

        return $collector->script;
    }

    #[Test]
    public function theMessageIsOneStrictStringLiteral(): void
    {
        $script = $this->render('Saved "x"); alert(1); ("<br>\x3cimg src=x onerror=alert(1)\x3e</script><b>ok</b>');

        self::assertSame(1, preg_match('/\$\.jGrowl\((".*?(?<!\\\\)"), \{/s', $script, $m), $script);
        $text = json_decode($m[1], false, 2, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('<img', $text);
        self::assertStringNotContainsString('<b>', $text, 'markup other than line breaks is text');
        self::assertStringContainsString('<br>', $text, 'line breaks survive');
        self::assertStringContainsString('&quot;x&quot;', $text);
        self::assertStringNotContainsString('</', $m[1], 'nothing can close the script element');
        self::assertArrayNotHasKey('redirect_message', $_SESSION);
    }

    #[Test]
    public function thePermissionSaverEscapesPostedNames(): void
    {
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/system/admin/groupperm.php');
        self::assertStringContainsString("\$esc = static fn (\$value): string => htmlspecialchars((string) \$value, ENT_QUOTES, 'UTF-8');", $source);
        self::assertStringNotContainsString("'<strong>' . \$perm_name . '</strong>'", $source);
        self::assertStringNotContainsString("\$perm_data['itemname'][\$item_id] . '</strong>'", $source);
        self::assertStringContainsString("sprintf(_MD_AM_PERMRESETNG, \$esc(\$module->getVar('name') . '(' . (string) \$perm_name . ')'));", $source);
    }
}
