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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\Upgrade\ScannerProcess;

/**
 * Scanner messages and reports name a template by its path inside the site,
 * also when the document root is a symlink: the preflight scan walks the
 * resolved root, so its pathnames start with the link's target.
 *
 * XOOPS_ROOT_PATH is a constant this suite already defines, so the symlinked
 * root runs in a child PHP process.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(ScannerProcess::class)]
final class ScannerProcessTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            if (is_link($path) || is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    #[Test]
    public function aPathUnderASymlinkedRootIsReportedRelativeToTheRoot(): void
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            self::markTestSkipped('creating a symlink needs a privilege on Windows');
        }
        $work = dirname(__DIR__) . '/tmp/work/' . uniqid('root_', true);
        mkdir($work . '/real', 0777, true);
        $this->cleanup[] = $work;
        $this->cleanup[] = $work . '/real';
        $real = (string) realpath($work . '/real');
        $link = $work . '/link';
        symlink($real, $link);
        $this->cleanup[] = $link;

        $script = $work . '/probe.php';
        file_put_contents($script, <<<'PHP'
            <?php
            define('XOOPS_ROOT_PATH', $argv[1]);
            require $argv[2];
            $probe = new class extends \Xoops\Upgrade\ScannerProcess {
                public function inspectFile(\SplFileInfo $fileInfo) {}
                public static function relative(string $pathname): string
                {
                    return self::relativeToRoot($pathname);
                }
            };
            echo $probe::relative($argv[3]);
            PHP);
        $this->cleanup[] = $script;

        $command = implode(' ', array_map('escapeshellarg', [
            PHP_BINARY,
            $script,
            $link,
            dirname(__DIR__, 2) . '/class/Xoops/Upgrade/ScannerProcess.php',
            $real . '/themes/demo/page.tpl',
        ]));
        exec($command, $out, $status);

        self::assertSame(0, $status);
        self::assertSame(['/themes/demo/page.tpl'], $out);
    }
}
