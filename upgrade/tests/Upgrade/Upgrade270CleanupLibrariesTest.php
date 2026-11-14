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
use Upgrade_270;
use Xoops\Upgrade\UpgradeControl;
use XoopsMySQLDatabase;

require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';
require_once dirname(__DIR__, 2) . '/upd_2.5.11-to-2.7.0/index.php';

/**
 * cleanuplibraries must remove the whole class/libraries/ tree a 2.7.x release
 * shipped, including its deny-all .htaccess, yet leave that .htaccess guarding
 * any files a site added there -- without the task reporting itself pending again.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class Upgrade270CleanupLibrariesTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = XOOPS_ROOT_PATH . '/class/libraries/';
        $this->removeTree(XOOPS_ROOT_PATH . '/class');
        mkdir($this->basePath . 'vendor/composer', 0777, true);
        mkdir($this->basePath . 'vendor/firebase/php-jwt', 0777, true);
        file_put_contents($this->basePath . '.htaccess', "order deny,allow\ndeny from all\n");
        file_put_contents($this->basePath . 'vendor/composer/ClassLoader.php', '<?php');
        file_put_contents($this->basePath . 'vendor/firebase/php-jwt/LICENSE', 'BSD');
    }

    protected function tearDown(): void
    {
        $this->removeTree(XOOPS_ROOT_PATH . '/class');
    }

    #[Test]
    public function shippedTreeIsRemovedCompletely(): void
    {
        $patch = $this->patch();

        self::assertFalse($patch->check_cleanuplibraries());
        self::assertTrue($patch->apply_cleanuplibraries());
        self::assertDirectoryDoesNotExist($this->basePath);
        self::assertTrue($patch->check_cleanuplibraries());
    }

    #[Test]
    public function htaccessStaysWhileSiteFilesRemain(): void
    {
        file_put_contents($this->basePath . 'mine.php', '<?php');
        $patch = $this->patch();

        self::assertTrue($patch->apply_cleanuplibraries());
        self::assertFileExists($this->basePath . '.htaccess');
        self::assertFileExists($this->basePath . 'mine.php');
        self::assertDirectoryDoesNotExist($this->basePath . 'vendor');
        self::assertTrue($patch->check_cleanuplibraries(), 'a guarded leftover must not re-flag the task');
    }

    private function patch(): Upgrade_270
    {
        return new Upgrade_270(
            $this->createMock(XoopsMySQLDatabase::class),
            $this->createMock(UpgradeControl::class),
        );
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
