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

namespace modulessystem;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/modules/system/class/fineuploadhandler.php';
require_once XOOPS_ROOT_PATH . '/modules/system/class/fineavataruploadhandler.php';

/**
 * The avatar upload handler enforces the site's avatar byte and pixel limits
 * on the server, before the file is moved or an avatar row is written.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class FineAvatarUploadLimitsTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        unset($_FILES['qqfile']);
    }

    /**
     * @param array<string, int> $limits
     */
    private function handler(array $limits): \SystemFineAvatarUploadHandler
    {
        return new class ((object) [], $limits) extends \SystemFineAvatarUploadHandler {
            /** @param array<string, int> $fixture */
            public function __construct(\stdClass $claims, private array $fixture)
            {
                parent::__construct($claims);
            }

            protected function limits(): array
            {
                return $this->fixture;
            }
        };
    }

    private function png(int $width, int $height): string
    {
        $file = tempnam(sys_get_temp_dir(), 'avt');
        $this->tempFiles[] = $file;
        // A PNG signature plus IHDR is all getimagesize() reads; no GD needed.
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        file_put_contents($file, "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr)));
        return $file;
    }

    /**
     * @return array<string, mixed>|false
     */
    private function store(\SystemFineAvatarUploadHandler $handler): array|false
    {
        return (new \ReflectionMethod($handler, 'storeUploadedFile'))->invoke($handler, '', 'image/png', 'u1');
    }

    #[Test]
    public function avatarByteLimitBecomesServerSizeLimit(): void
    {
        self::assertSame(35000, $this->handler(['avatar_maxsize' => 35000])->sizeLimit);
    }

    #[Test]
    public function overSizedAvatarIsRejectedBeforeStoring(): void
    {
        $tmp = $this->png(200, 10);
        $_FILES['qqfile'] = ['tmp_name' => $tmp, 'name' => 'a.png'];
        $result = $this->store($this->handler(['avatar_maxsize' => 0, 'avatar_width' => 128, 'avatar_height' => 128]));

        self::assertIsArray($result);
        self::assertArrayHasKey('error', $result);
        self::assertArrayNotHasKey('success', $result);
        self::assertFileExists($tmp, 'the rejected file must not be moved');
    }

    #[Test]
    public function nonImageAvatarIsRejectedBeforeStoring(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'avt');
        $this->tempFiles[] = $file;
        file_put_contents($file, 'not an image at all');
        $_FILES['qqfile'] = ['tmp_name' => $file, 'name' => 'a.png'];
        $result = $this->store($this->handler([]));

        self::assertIsArray($result);
        self::assertArrayHasKey('error', $result);
    }
}
