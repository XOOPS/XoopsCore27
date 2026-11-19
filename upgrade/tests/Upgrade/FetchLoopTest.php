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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A loop written as while (false !== (list($a) = $db->fetchRow($result)))
 * destructures the false that ends it, which PHP 8.5 reports with a warning.
 * The wizard's loops fetch the row into a variable and destructure it inside.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class FetchLoopTest extends TestCase
{
    private const DESTRUCTURING_LOOP = '/while\s*\(.*(?:\blist\s*\([^)]*\)|\[[^\]]*\])\s*=\s*\$[\w>-]+->fetch\w+\(/';

    #[Test]
    public function thePatternFindsBothSpellings(): void
    {
        self::assertSame(1, preg_match(self::DESTRUCTURING_LOOP, 'while (false !== (list($a, $b) = $this->db->fetchRow($result))) {'));
        self::assertSame(1, preg_match(self::DESTRUCTURING_LOOP, 'while ([$a] = $db->fetchRow($result)) {'));
        self::assertSame(0, preg_match(self::DESTRUCTURING_LOOP, 'while (false !== ($row = $this->db->fetchRow($result))) {'));
    }

    #[Test]
    public function noWizardLoopDestructuresTheFetchInItsCondition(): void
    {
        $root  = dirname(__DIR__, 2);
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
            if ('php' !== $file->getExtension() || str_starts_with($relative, '/tests/')) {
                continue;
            }
            foreach (file($file->getPathname()) ?: [] as $number => $line) {
                if (1 === preg_match(self::DESTRUCTURING_LOOP, $line)) {
                    $found[] = $relative . ':' . ($number + 1);
                }
            }
        }

        self::assertSame([], $found);
    }
}
