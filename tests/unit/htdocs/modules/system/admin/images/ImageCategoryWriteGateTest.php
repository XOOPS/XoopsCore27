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

namespace modulessystem\admin\images;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__, 2) . '/SourceFileTestTrait.php';
require_once XOOPS_ROOT_PATH . '/kernel/image.php';

/**
 * A delegated image administrator may change only images and categories they
 * hold WRITE on: image operations check the image's stored category, a save
 * also checks the category the image is moved to, and the category display
 * toggle checks that category. The gate is sliced from admin/images/main.php
 * and executed as written; the bootstrap's redirect_header() throws.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ImageCategoryWriteGateTest extends TestCase
{
    use SourceFileTestTrait;

    public const WRITABLE = 5;
    public const FOREIGN  = 6;
    /** A category id that every right check grants (as for the webmaster) but that does not exist. */
    public const MISSING  = 7;

    private function gate(): string
    {
        $this->loadSourceFile('htdocs/modules/system/admin/images/main.php');
        $from  = '// check WRITE right by category before continue';
        $to    = '// Only website administator';
        $start = strpos($this->sourceContent, $from);
        self::assertNotFalse($start, 'start marker missing');
        $end = strpos($this->sourceContent, $to, $start);
        self::assertNotFalse($end, 'end marker missing');
        $handlerLines = ["\$image_handler = xoops_getHandler('image');", "\$imgcat_handler = xoops_getHandler('imagecategory');"];
        foreach ($handlerLines as $line) {
            self::assertStringContainsString($line, $this->sourceContent);
        }

        // The handlers come from the test, not xoops_getHandler().
        return str_replace($handlerLines, '', substr($this->sourceContent, $start, $end - $start));
    }

    /**
     * @return array<string, array{string, int|null, int, bool}> [op, image's category (null = no image), posted imgcat_id, allowed]
     */
    public static function requests(): array
    {
        return [
            'toggle image in writable category' => ['display_img', self::WRITABLE, 0, true],
            'toggle image in foreign category'  => ['display_img', self::FOREIGN, 0, false],
            'edit form for foreign image'       => ['editimg', self::FOREIGN, 0, false],
            'delete confirm for foreign image'  => ['delfile', self::FOREIGN, 0, false],
            'delete foreign image'              => ['delfileok', self::FOREIGN, 0, false],
            'unknown image'                     => ['display_img', null, 0, false],
            'image stored in category 0'        => ['display_img', 0, 0, false],
            'save image stored in category 0'   => ['save', 0, self::WRITABLE, false],
            'save within writable category'     => ['save', self::WRITABLE, self::WRITABLE, true],
            'save moves image to foreign cat'   => ['save', self::WRITABLE, self::FOREIGN, false],
            'save pulls image from foreign cat' => ['save', self::FOREIGN, self::WRITABLE, false],
            'save moves image to missing cat'   => ['save', self::WRITABLE, self::MISSING, false],
            'toggle writable category'          => ['display_cat', 0, self::WRITABLE, true],
            'toggle foreign category'           => ['display_cat', 0, self::FOREIGN, false],
        ];
    }

    #[Test]
    #[DataProvider('requests')]
    public function onlyWritableCategoriesCanBeChanged(string $op, ?int $imageCategory, int $imgcat_id, bool $allowed): void
    {
        $image_id      = 42;
        $groups        = [2];
        $xoopsModule   = new class {
            public function mid(): int
            {
                return 1;
            }
        };
        $gperm_handler = new class {
            public function checkRight(string $name, int $id, mixed $groups, int $mid): bool
            {
                // Like XoopsGroupPermHandler: item 0 is "any item" and passes.
                return 'imgcat_write' === $name
                    && in_array($id, [0, ImageCategoryWriteGateTest::WRITABLE, ImageCategoryWriteGateTest::MISSING], true);
            }
        };
        $imgcat_handler = new class {
            public function get(int $id): mixed
            {
                return in_array($id, [ImageCategoryWriteGateTest::WRITABLE, ImageCategoryWriteGateTest::FOREIGN], true)
                    ? new \stdClass() : false;
            }
        };
        $image_handler = new class($imageCategory) {
            public function __construct(private ?int $category)
            {
            }

            public function get(int $id): mixed
            {
                if (null === $this->category) {
                    return false;
                }
                $image = new \XoopsImage();
                $image->setVar('image_id', $id);
                $image->setVar('imgcat_id', $this->category);

                return $image;
            }
        };

        if (!$allowed) {
            $this->expectException(\RedirectHeaderException::class);
        }
        eval($this->gate());
        self::assertTrue($allowed);
    }
}
