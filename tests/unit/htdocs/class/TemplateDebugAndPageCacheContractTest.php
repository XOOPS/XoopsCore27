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
 * Smarty debugging audience and page-cache identity.
 *
 * - XoopsTpl switched on ?SMARTY_DEBUG for every visitor whenever debug mode
 *   was on, ignoring the debugLevel audience the logger obeys.
 * - The page cache keyed a URI by 8 hex characters of its MD5, so two URIs
 *   could share one cached page.
 * - The private-message reader could be page-cached under a URL that shows
 *   each member their own mail.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TemplateDebugAndPageCacheContractTest extends TestCase
{
    private static function source(string $path): string
    {
        $source = file_get_contents(XOOPS_ROOT_PATH . '/' . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    #[Test]
    public function smartyDebuggingFollowsTheLoggerAudience(): void
    {
        $source = self::source('class/template.php');
        $gate   = strpos($source, "if (\$xoopsConfig['debug_mode'] && \$debugAudience) {");
        self::assertNotFalse($gate, 'debugging is gated on the audience');
        // No permission lookup on every page while debugging is off.
        self::assertStringContainsString("\$debugAudience = !empty(\$xoopsConfig['debug_mode']) && match (\$debugLevel) {", $source);
        // System-admin right, not $xoopsUserIsAdmin: include/common.php has
        // replaced that with the current module's admin right by now.
        foreach (["2       => \$viewer instanceof \\XoopsUser && \$viewer->isAdmin(1),", "1       => is_object(\$viewer),"] as $line) {
            $at = strpos($source, $line);
            self::assertNotFalse($at, $line);
            self::assertLessThan($gate, $at, $line);
        }
        self::assertStringNotContainsString("xoopsUserIsAdmin'])", substr($source, 0, $gate));
        self::assertSame(1, substr_count($source, "\$this->debugging_ctrl = 'URL';"));
        self::assertGreaterThan($gate, strpos($source, "\$this->debugging_ctrl = 'URL';"));
    }

    #[Test]
    public function thePageCacheKeysTheWholeUriDigest(): void
    {
        $source = self::source('class/theme.php');
        self::assertStringContainsString("\$this->generateCacheId('page_' . hash('sha256', \$uri));", $source);
        self::assertStringNotContainsString('substr(md5($uri)', $source);
    }

    #[Test]
    public function thePrivateMessageReaderIsNeverPageCached(): void
    {
        $source  = self::source('modules/pm/readpmsg.php');
        $disable = strpos($source, "\$GLOBALS['xoopsConfig']['module_cache'][\$GLOBALS['xoopsModule']->getVar('mid')] = 0;");
        $header  = strpos($source, "include \$GLOBALS['xoops']->path('header.php');");
        self::assertNotFalse($disable);
        self::assertNotFalse($header);
        self::assertLessThan($header, $disable, 'caching is off before header.php checks the cache');
    }
}
