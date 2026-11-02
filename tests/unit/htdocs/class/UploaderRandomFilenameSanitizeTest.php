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
 * Random-filename mode copied the original name into targetFileName before
 * sanitizeMultipleExtensions() ran, and only mediaName was sanitized, so
 * "x.php.jpg" was saved as "<random>--x.php.jpg".
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/uploader.php';

#[CoversClass(XoopsMediaUploader::class)]
final class UploaderRandomFilenameSanitizeTest extends TestCase
{
    #[Test]
    public function theSavedNameLosesTheExecutableInnerExtension(): void
    {
        $uploader = (new ReflectionClass(XoopsMediaUploader::class))->newInstanceWithoutConstructor();
        $uploader->extensionsToBeSanitized = ['php', 'phtml'];
        $uploader->mediaName      = 'shell.php.jpg';
        $uploader->targetFileName = '65a1b2c3d4e5f--shell.php.jpg';

        $uploader->sanitizeMultipleExtensions();

        self::assertSame('shell_php.jpg', $uploader->mediaName);
        self::assertSame('65a1b2c3d4e5f--shell_php.jpg', $uploader->targetFileName);
    }

    #[Test]
    public function adjacentInnerExtensionsAreAllSanitized(): void
    {
        $uploader = (new ReflectionClass(XoopsMediaUploader::class))->newInstanceWithoutConstructor();
        $uploader->extensionsToBeSanitized = ['php', 'phtml'];
        $sanitize = new ReflectionMethod(XoopsMediaUploader::class, 'sanitizeExtensionsIn');

        self::assertSame('shell_php_php.jpg', $sanitize->invoke($uploader, 'shell.php.php.jpg'));
        self::assertSame('shell_phtml_phtml.jpg', $sanitize->invoke($uploader, 'shell.phtml.phtml.jpg'));
        self::assertSame('shell_php_php.jpg', $sanitize->invoke($uploader, 'shell.PHP.php.jpg'));
        self::assertSame('photo.php', $sanitize->invoke($uploader, 'photo.php'), 'the final extension is the allowlist\'s job');
    }

    /**
     * _copyFile() strips characters after sanitizeMultipleExtensions() ran,
     * which can join a new inner extension (shell.p#hp.jpg): the final saved
     * name is sanitized again before the file is moved.
     */
    #[Test]
    public function theFinalSavedNameIsSanitizedBeforeTheMove(): void
    {
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/class/uploader.php');
        $strip  = strpos($source, "\$this->savedFileName = preg_replace(\"/[^a-zA-Z0-9\\._-]/\", '', \$this->savedFileName);");
        $again  = strpos($source, '$this->savedFileName = $this->sanitizeExtensionsIn((string) $this->savedFileName);');
        $move   = strpos($source, 'move_uploaded_file($this->mediaTmpName, $this->savedDestination)');
        self::assertNotFalse($strip);
        self::assertNotFalse($again);
        self::assertNotFalse($move);
        self::assertLessThan($again, $strip);
        self::assertLessThan($move, $again);
    }

    #[Test]
    public function noTargetNameStaysNoTargetName(): void
    {
        $uploader = (new ReflectionClass(XoopsMediaUploader::class))->newInstanceWithoutConstructor();
        $uploader->extensionsToBeSanitized = ['php'];
        $uploader->mediaName = 'a.php.png';

        $uploader->sanitizeMultipleExtensions();

        self::assertNull($uploader->targetFileName);
    }
}
