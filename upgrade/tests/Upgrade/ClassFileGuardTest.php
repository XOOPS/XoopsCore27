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

namespace Xoops\Upgrade\Tests\Upgrade;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The wizard's class files are reachable by URL for as long as the upgrade
 * folder is on the server. A class file requested on its own must stop at
 * the XOOPS_ROOT_PATH guard: without it a subclass fails on its missing
 * parent class and the error names the file's location on the server.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ClassFileGuardTest extends TestCase
{
    private const GUARD = "defined('XOOPS_ROOT_PATH') || exit('Restricted access');";

    /**
     * @return iterable<string, array{string}>
     */
    public static function classFiles(): iterable
    {
        foreach (glob(dirname(__DIR__, 2) . '/class/Xoops/Upgrade/*.php') ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    #[Test]
    #[DataProvider('classFiles')]
    public function aClassFileStopsAtTheGuardBeforeItDeclaresItsClass(string $file): void
    {
        $source = (string) file_get_contents($file);
        $guard  = strpos($source, self::GUARD);
        self::assertNotFalse($guard, basename($file) . ' has no XOOPS_ROOT_PATH guard');

        self::assertSame(1, preg_match('/^(?:abstract |final )?class /m', $source, $match, PREG_OFFSET_CAPTURE));
        self::assertLessThan($match[0][1], $guard, basename($file) . ' declares its class before the guard');
    }

    #[Test]
    public function theClassFolderIsNotEmpty(): void
    {
        self::assertGreaterThan(10, count(iterator_to_array(self::classFiles())));
    }

    /**
     * index.php and preflight.php load this file before mainfile.php, so
     * XOOPS_ROOT_PATH is not defined yet: a guard here would stop the wizard.
     */
    #[Test]
    public function theFatalErrorHandlerLoadsBeforeMainfileAndSoCarriesNoGuard(): void
    {
        $upgrade = dirname(__DIR__, 2);
        self::assertStringNotContainsString(
            "defined('XOOPS_ROOT_PATH')",
            (string) file_get_contents($upgrade . '/class/fatal_error_handler.php')
        );

        foreach (['index.php', 'preflight.php'] as $entry) {
            $source  = (string) file_get_contents($upgrade . '/' . $entry);
            $handler = strpos($source, "/class/fatal_error_handler.php'");
            $main    = strpos($source, "/checkmainfile.php'");
            self::assertNotFalse($handler, $entry . ' no longer loads the fatal error handler');
            self::assertNotFalse($main, $entry . ' no longer loads checkmainfile.php');
            self::assertLessThan($main, $handler, $entry . ' loads the handler after mainfile; the guard question changes');
        }
    }
}
