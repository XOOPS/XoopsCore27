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

    private function removeFile(): void
    {
        if (is_file($this->file) || is_link($this->file)) {
            unlink($this->file);
        }
    }
}
