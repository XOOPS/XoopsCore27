<?php
/**
 * File cache garbage collection and the lock setting.
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Tests
 * @package   class
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

declare(strict_types=1);

namespace xoopscache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use XoopsCacheFile;

/**
 * gc() decides expiry from each entry's own stored time, and the engine's
 * lock setting works for reads and collection.
 */
#[CoversClass(XoopsCacheFile::class)]
final class XoopsCacheFileGcTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/cache/xoopscache.php';
        require_once XOOPS_ROOT_PATH . '/class/cache/file.php';
        // XoopsFolderHandler only accepts forward-slash Windows paths.
        $this->dir = str_replace('\\', '/', sys_get_temp_dir()) . '/xoops-cachegc-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function engine(array $settings = []): XoopsCacheFile
    {
        $engine = new XoopsCacheFile();
        $this->assertTrue($engine->init($settings + ['path' => $this->dir]));

        return $engine;
    }

    #[Test]
    public function gcRemovesAnExpiredEntryYoungerThanTheEngineDuration(): void
    {
        // The engine duration defaults to a year; the entries carry their own.
        $engine = $this->engine();
        $this->assertTrue($engine->write('fresh', 'kept', 3600));
        $this->assertTrue($engine->write('stale', 'gone', -10));

        $this->assertTrue($engine->gc());

        $this->assertFileExists($this->dir . '/xoops_fresh.php');
        $this->assertFileDoesNotExist($this->dir . '/xoops_stale.php');
    }

    #[Test]
    public function lockedEngineReadsAndCollects(): void
    {
        $engine = $this->engine(['lock' => true]);
        $this->assertTrue($engine->write('fresh', 'kept', 3600));
        $this->assertTrue($engine->write('stale', 'gone', -10));

        $this->assertSame('kept', $engine->read('fresh'));
        $this->assertTrue($engine->gc());

        $this->assertFileExists($this->dir . '/xoops_fresh.php');
        $this->assertFileDoesNotExist($this->dir . '/xoops_stale.php');
    }

    #[Test]
    public function gcKeepsAnEntryWithoutACompleteExpiryLine(): void
    {
        // An empty or cut-off header is what a write in progress looks like.
        $engine = $this->engine();
        file_put_contents($this->dir . '/xoops_empty.php', '');
        file_put_contents($this->dir . '/xoops_partial.php', '12345');
        file_put_contents($this->dir . '/xoops_stale.php', "12345\nx\n");
        // write() with a large negative duration stores a negative expiry.
        file_put_contents($this->dir . '/xoops_negative.php', "-60\nx\n");

        $this->assertTrue($engine->gc());

        $this->assertFileExists($this->dir . '/xoops_empty.php');
        $this->assertFileExists($this->dir . '/xoops_partial.php');
        $this->assertFileDoesNotExist($this->dir . '/xoops_stale.php');
        $this->assertFileDoesNotExist($this->dir . '/xoops_negative.php');
    }

    #[Test]
    public function gcTakesTheLockSettingWithoutAnEarlierReadOrWrite(): void
    {
        // gc() can be the first call on a fresh engine: XoopsCache::engine() rolls for it at init.
        $engine = $this->engine(['lock' => true]);
        file_put_contents($this->dir . '/xoops_stale.php', "12345\nx\n");

        $this->assertTrue($engine->gc());

        $this->assertFileDoesNotExist($this->dir . '/xoops_stale.php');
        $file = (new \ReflectionProperty(XoopsCacheFile::class, 'file'))->getValue($engine);
        $this->assertTrue($file->lock);
    }
}
