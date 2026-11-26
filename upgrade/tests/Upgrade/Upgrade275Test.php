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
use Upgrade_275;
use Xoops\Upgrade\UpgradeControl;
use XoopsMySQLDatabase;

require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';
require_once dirname(__DIR__, 2) . '/upd_2.7.4-to-2.7.5/index.php';
require_once dirname(__DIR__, 2) . '/language/english/upd_2.7.4-to-2.7.5.php';

/**
 * datahtaccess rewrites only the stock Apache 2.2 xoops_data/.htaccess, leaves every
 * other file alone, and never holds the upgrade when the file cannot be written.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class Upgrade275Test extends TestCase
{
    private const LEGACY = "order deny,allow\ndeny from all\n";

    private string $file;

    protected function setUp(): void
    {
        if (!is_dir(XOOPS_VAR_PATH)) {
            mkdir(XOOPS_VAR_PATH, 0777, true);
        }
        $this->file = XOOPS_VAR_PATH . '/.htaccess';
        $this->removeFile();
        unset($_SESSION['xoops_upgrade_275_datahtaccess_skipped']);
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            chmod($this->file, 0600);
        }
        $this->removeFile();
        unset($_SESSION['xoops_upgrade_275_datahtaccess_skipped']);
    }

    #[Test]
    public function theShippedFileIsTheOneTheUpgradeWrites(): void
    {
        $shipped = (string) file_get_contents(dirname(__DIR__, 3) . '/htdocs/xoops_data/.htaccess');

        self::assertSame(Upgrade_275::DATA_HTACCESS, str_replace("\r\n", "\n", $shipped));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function legacyProvider(): array
    {
        return [
            'as shipped'                  => [self::LEGACY],
            'Windows line endings'        => ["order deny,allow\r\ndeny from all\r\n"],
            'capitalised and spaced'      => ["  Order Deny,Allow\n\n  Deny  from  all  "],
            'byte-order mark'             => ["\xEF\xBB\xBF" . self::LEGACY],
        ];
    }

    #[Test]
    #[DataProvider('legacyProvider')]
    public function theLegacyFileIsRewritten(string $legacy): void
    {
        file_put_contents($this->file, $legacy);
        $patch = $this->patch();

        self::assertFalse($patch->check_datahtaccess());
        self::assertTrue($patch->apply_datahtaccess());
        self::assertSame(Upgrade_275::DATA_HTACCESS, file_get_contents($this->file));
        self::assertTrue($patch->check_datahtaccess());
        self::assertStringContainsString('text-success', $patch->message());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function siteFileProvider(): array
    {
        return [
            'already the new form' => [Upgrade_275::DATA_HTACCESS],
            'a comment added'      => ["# mine\n" . self::LEGACY],
            'an extra rule'        => [self::LEGACY . "Allow from 10.0.0.0/8\n"],
            'only one line'        => ["deny from all\n"],
            'empty'                => [''],
        ];
    }

    #[Test]
    #[DataProvider('siteFileProvider')]
    public function anyOtherFileIsLeftAlone(string $content): void
    {
        file_put_contents($this->file, $content);
        $patch = $this->patch();

        self::assertTrue($patch->check_datahtaccess());
        self::assertTrue($patch->apply_datahtaccess());
        self::assertSame($content, file_get_contents($this->file));
        self::assertSame('', $patch->message());
    }

    #[Test]
    public function aSecondRunLeavesTheRewrittenFileAlone(): void
    {
        file_put_contents($this->file, self::LEGACY);
        self::assertTrue($this->patch()->apply(), 'first run rewrites the legacy file');
        touch($this->file, 1000000000);
        clearstatcache(true, $this->file);

        $second = $this->patch();

        self::assertTrue($second->check_datahtaccess(), 'the second run finds nothing to do');
        self::assertTrue($second->apply());
        self::assertSame('', $second->message());
        clearstatcache(true, $this->file);
        self::assertSame(Upgrade_275::DATA_HTACCESS, file_get_contents($this->file));
        self::assertSame(1000000000, filemtime($this->file), 'the file was not written again');
    }

    #[Test]
    public function aMissingFileIsNotCreated(): void
    {
        $patch = $this->patch();

        self::assertTrue($patch->check_datahtaccess());
        self::assertTrue($patch->apply_datahtaccess());
        self::assertFileDoesNotExist($this->file);
    }

    #[Test]
    public function anUnwritableFileIsReportedWithoutHoldingTheUpgrade(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            self::markTestSkipped('needs POSIX file permissions and a non-root user');
        }
        // The file is replaced by renaming a new one over it, so what stops the task is
        // a folder it cannot create files in, not the file's own mode.
        file_put_contents($this->file, self::LEGACY);
        chmod(XOOPS_VAR_PATH, 0500);
        try {
            $patch = $this->patch();

            self::assertFalse($patch->check_datahtaccess());
            self::assertTrue($patch->apply_datahtaccess());
            self::assertSame(self::LEGACY, file_get_contents($this->file));
            self::assertStringContainsString('text-danger', $patch->message());
            self::assertTrue($patch->check_datahtaccess(), 'told once, the task must let the upgrade finish');
        } finally {
            chmod(XOOPS_VAR_PATH, 0755);
        }

        // Fixed by hand later in the session: the flag is dropped and the check reads the file again.
        file_put_contents($this->file, Upgrade_275::DATA_HTACCESS);
        self::assertTrue($patch->check_datahtaccess());
        self::assertArrayNotHasKey('xoops_upgrade_275_datahtaccess_skipped', $_SESSION ?? []);
    }

    #[Test]
    public function aReadOnlyFileInAWritableFolderIsReplacedAndKeepsItsMode(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('needs POSIX file modes');
        }
        file_put_contents($this->file, self::LEGACY);
        chmod($this->file, 0440);

        self::assertTrue($this->patch()->apply_datahtaccess());
        self::assertSame(Upgrade_275::DATA_HTACCESS, file_get_contents($this->file));
        clearstatcache();
        self::assertSame(0440, fileperms($this->file) & 0777);
    }

    /**
     * @return array<string, array{int|false}>
     */
    public static function failingWriteProvider(): array
    {
        return [
            'disk full after 80 bytes' => [80],
            'nothing written'          => [0],
            'write refused'            => [false],
        ];
    }

    /**
     * A write cut off by a full disk must not reach the live file: a truncated .htaccess
     * protects nothing and no longer looks like the legacy file, so it would never be
     * retried.
     */
    #[Test]
    #[DataProvider('failingWriteProvider')]
    public function aFailedWriteLeavesTheOriginalUntouched(int|false $bytes): void
    {
        file_put_contents($this->file, self::LEGACY);
        $patch = new class (
            $this->createMock(XoopsMySQLDatabase::class),
            $this->createMock(UpgradeControl::class),
            $bytes,
        ) extends Upgrade_275 {
            public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control, private int|false $bytes)
            {
                parent::__construct($db, $control);
            }

            protected function writeFile(string $path, string $content): int|false
            {
                if (false === $this->bytes) {
                    return false;
                }
                file_put_contents($path, substr($content, 0, $this->bytes));

                return $this->bytes;
            }
        };

        self::assertTrue($patch->apply_datahtaccess());
        self::assertSame(self::LEGACY, file_get_contents($this->file));
        self::assertSame([], glob($this->file . '.tmp-*'));
        self::assertStringContainsString('text-danger', $patch->message());
    }

    private function patch(): Upgrade_275
    {
        return new Upgrade_275(
            $this->createMock(XoopsMySQLDatabase::class),
            $this->createMock(UpgradeControl::class),
        );
    }

    #[Test]
    public function theWizardRetiresObsoleteAssetsAndPreservesCustomizedThemesOnRerun(): void
    {
        self::assertSame(realpath(dirname(__DIR__)), realpath(XOOPS_ROOT_PATH), 'Use the upgrade test sandbox.');
        $obsolete = [];
        foreach (['install', 'upgrade'] as $wizard) {
            foreach (['css/bootstrap.css', 'css/bootstrap.min.css', 'js/bootstrap.js', 'js/bootstrap.min.js'] as $file) {
                $obsolete[] = "$wizard/assets/$file";
            }
            foreach (['eot', 'svg', 'ttf', 'woff', 'woff2'] as $extension) {
                $obsolete[] = "$wizard/assets/fonts/glyphicons-halflings-regular.$extension";
            }
        }
        $obsolete[] = 'upgrade/assets/js/jquery.js';
        $obsolete[] = 'upgrade/assets/js/xo-installer.js';
        foreach (['tinymce5', 'tinymce7'] as $editor) {
            $manager = "class/xoopseditor/$editor/js/tinymce/plugins/xoopsimagemanager";
            $obsolete[] = "$manager/css/bootstrap.min.css";
            $obsolete[] = "$manager/js/bootstrap.bundle.min.js";
        }
        $obsolete[] = 'class/xoopseditor/tinymce5/js/tinymce/plugins/xoopsemoticons/css/bootstrap.min.css';
        $themeCss = 'themes/xbootstrap5/css/bootstrap-reboot.min.css';
        $obsolete[] = $themeCss;
        $css = file_get_contents(dirname(__DIR__) . '/fixtures/bootstrap-reboot-5.3.8.min.css');
        self::assertIsString($css);
        $contents = [
            $themeCss => str_replace("\n", "\r\n", str_replace("\r\n", "\n", $css)),
            'themes/xswatch5/css/bootstrap.min.css' => $css . '/* site customization */',
            'themes/xbootstrap5/css/bootstrap.min.css' => $css, // Stock CSS, wrong path.
            'themes/xswatch5/css-cerulean/bootstrap.min.css' => $css,
        ];
        $keep = array_merge(
            ['install/assets/js/jquery.min.js', 'install/assets/js/xo-installer.js', 'upgrade/assets/css/site.css', 'media/bootstrap5/css/bootstrap.min.css'],
            array_diff(array_keys($contents), $obsolete)
        );

        $files = $dirs = [];
        try {
            foreach (array_merge($obsolete, $keep) as $relative) {
                $path = XOOPS_ROOT_PATH . '/' . $relative;
                $missing = [];
                for ($dir = dirname($path); !is_dir($dir); $dir = dirname($dir)) {
                    $missing[] = $dir;
                }
                foreach (array_reverse($missing) as $dir) {
                    self::assertTrue(mkdir($dir));
                    $dirs[] = $dir;
                }
                self::assertFileDoesNotExist($path);
                $content = $contents[$relative] ?? 'asset';
                self::assertSame(strlen($content), file_put_contents($path, $content));
                $files[] = $path;
            }

            $patch = $this->patch();
            self::assertContains('bootstrapassets', $patch->isApplied()->tasks);
            $blocked = $this->getMockBuilder(Upgrade_275::class)
                ->setConstructorArgs([$this->createMock(XoopsMySQLDatabase::class), $this->createMock(UpgradeControl::class)])
                ->onlyMethods(['removeLeftover'])
                ->getMock();
            $blocked->method('removeLeftover')->willReturn(false);
            self::assertFalse($blocked->apply());
            self::assertStringContainsString('install/assets/css/bootstrap.css', $blocked->message());
            self::assertContains('bootstrapassets', $blocked->isApplied()->tasks);

            self::assertTrue($patch->apply());
            foreach ($obsolete as $relative) {
                self::assertFileDoesNotExist(XOOPS_ROOT_PATH . '/' . $relative);
                $packagePath = str_starts_with($relative, 'upgrade/') ? $relative : 'htdocs/' . $relative;
                self::assertFileDoesNotExist(dirname(__DIR__, 3) . '/' . $packagePath);
            }
            foreach ($keep as $relative) {
                self::assertSame($contents[$relative] ?? 'asset', file_get_contents(XOOPS_ROOT_PATH . '/' . $relative));
            }
            $custom = $css . '/* customized reboot */';
            self::assertSame(strlen($custom), file_put_contents(XOOPS_ROOT_PATH . '/' . $themeCss, $custom));
            self::assertTrue($patch->isApplied()->applied);
            self::assertTrue($patch->apply());
            self::assertSame($custom, file_get_contents(XOOPS_ROOT_PATH . '/' . $themeCss));
        } finally {
            foreach ($files as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            foreach (array_reverse($dirs) as $dir) {
                rmdir($dir);
            }
        }
    }

    private function removeFile(): void
    {
        if (is_file($this->file) || is_link($this->file)) {
            unlink($this->file);
        }
    }
}
