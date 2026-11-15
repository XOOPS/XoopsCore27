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

namespace Tests\Unit\Install;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/install/class/pathcontroller.php';

/**
 * The installer's path page must really test that the root and data write
 * targets are writable. It used to return before testing anything, and the
 * helper behind it treated mainfile.php as a directory to create. Every case
 * runs on a temporary tree, never on the checkout.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class PathControllerPermissionsTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/xoops-perms-' . bin2hex(random_bytes(6));
        mkdir($this->base . '/root/uploads', 0777, true);
        mkdir($this->base . '/data', 0777, true);
        mkdir($this->base . '/lib', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->base);
    }

    private function remove(string $path): void
    {
        if (is_link($path)) {
            unlink($path);

            return;
        }
        if (is_file($path)) {
            chmod($path, 0666);
            unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        chmod($path, 0777);
        foreach (scandir($path) ?: [] as $entry) {
            if ('.' !== $entry && '..' !== $entry) {
                $this->remove($path . '/' . $entry);
            }
        }
        rmdir($path);
    }

    private function controller(): \PathController
    {
        $controller = (new \ReflectionClass(\PathController::class))->newInstanceWithoutConstructor();
        $controller->xoopsPath = [
            'root' => $this->base . '/root',
            'data' => $this->base . '/data',
            'lib'  => $this->base . '/lib',
        ];
        $controller->dataPath = [
            'caches'    => ['smarty_cache', 'smarty_compile', 'xoops_cache'],
            'configs'   => ['captcha', 'textsanitizer'],
            'data'      => null,
            'protector' => null,
        ];

        return $controller;
    }

    /**
     * Probe instead of guessing the platform: Windows ignores a read-only directory,
     * and root (with or without ext-posix to detect it) writes through one.
     */
    private function requireEnforcedPermissions(): void
    {
        $probe = $this->base . '/probe';
        mkdir($probe);
        chmod($probe, 0555);
        clearstatcache();
        $enforced = !is_writable($probe);
        chmod($probe, 0777);
        rmdir($probe);
        if (!$enforced) {
            self::markTestSkipped('this user can write to a read-only directory (Windows or root).');
        }
    }

    #[Test]
    public function writableTargetsPassAndMissingDataDirectoriesAreCreated(): void
    {
        $controller = $this->controller();

        self::assertTrue($controller->checkPermissions('root'));
        self::assertTrue($controller->checkPermissions('data'));
        self::assertNull($controller->permErrors['root']);
        self::assertNull($controller->permErrors['data']);
        foreach (['caches/smarty_cache', 'caches/smarty_compile', 'caches/xoops_cache', 'configs/captcha', 'configs/textsanitizer', 'data', 'protector'] as $dir) {
            self::assertDirectoryExists($this->base . '/data/' . $dir);
        }
    }

    #[Test]
    public function aMissingMainfileIsNeitherCreatedNorTurnedIntoADirectory(): void
    {
        $result = $this->controller()->makeWritable($this->base . '/root/mainfile.php');

        self::assertNotFalse($result, 'a writable root is enough for the wizard to write mainfile.php later');
        self::assertFileDoesNotExist($this->base . '/root/mainfile.php');
    }

    #[Test]
    public function anExistingMainfileIsCheckedAsAFile(): void
    {
        $mainfile = $this->base . '/root/mainfile.php';
        file_put_contents($mainfile, '<?php');
        $controller = $this->controller();

        self::assertNotFalse($controller->makeWritable($mainfile));
        self::assertFileExists($mainfile);

        chmod($mainfile, 0444);
        clearstatcache();
        if (is_writable($mainfile)) {
            self::markTestSkipped('this user can write a read-only file (root).');
        }
        self::assertFalse($controller->makeWritable($mainfile), 'a read-only mainfile.php is reported');
        self::assertSame(0444, fileperms($mainfile) & 0777, 'permissions are never widened');
    }

    #[Test]
    public function aDanglingMainfileSymlinkIsReportedInsteadOfFollowed(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('symlink() needs extra privileges on Windows.');
        }
        mkdir($this->base . '/elsewhere');
        symlink($this->base . '/elsewhere/mainfile.php', $this->base . '/root/mainfile.php');

        self::assertFalse($this->controller()->makeWritable($this->base . '/root/mainfile.php'));
        self::assertFileDoesNotExist($this->base . '/elsewhere/mainfile.php');
    }

    #[Test]
    public function aTargetOfTheWrongTypeIsReported(): void
    {
        mkdir($this->base . '/root/mainfile.php');
        rmdir($this->base . '/root/uploads');
        touch($this->base . '/root/uploads');
        $controller = $this->controller();

        self::assertTrue($controller->checkPermissions('root'));
        self::assertSame(
            [$this->base . '/root/mainfile.php' => false, $this->base . '/root/uploads' => false],
            $controller->permErrors['root']
        );
    }

    #[Test]
    public function libHasNoWriteTargets(): void
    {
        $controller = $this->controller();

        self::assertTrue($controller->checkPermissions('lib'));
        self::assertSame(['root' => null, 'data' => null], $controller->permErrors);
    }

    #[Test]
    public function anUnwritableRootIsReportedWithoutWideningIt(): void
    {
        $this->requireEnforcedPermissions();
        chmod($this->base . '/root/uploads', 0555);
        chmod($this->base . '/root', 0555);
        $controller = $this->controller();

        self::assertTrue($controller->checkPermissions('root'));
        self::assertSame(
            [$this->base . '/root/mainfile.php' => false, $this->base . '/root/uploads' => false],
            $controller->permErrors['root']
        );
        self::assertSame(0555, fileperms($this->base . '/root/uploads') & 0777);
    }

    #[Test]
    public function anUnwritableDataPathReportsItsTargetsInsteadOfThrowing(): void
    {
        $this->requireEnforcedPermissions();
        chmod($this->base . '/data', 0555);
        $controller = $this->controller();

        self::assertTrue($controller->checkPermissions('data'));
        self::assertNotEmpty($controller->permErrors['data']);
        self::assertNotContains(true, array_map('is_string', $controller->permErrors['data']), 'every target is reported as not writable');
    }
}
