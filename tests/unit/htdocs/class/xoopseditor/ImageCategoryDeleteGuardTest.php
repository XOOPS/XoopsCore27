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

namespace Tests\Unit\ClassDir\XoopsEditor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Deleting an image category deletes its images first. The image and category
 * tables have no foreign key, so when an image row cannot be deleted the
 * category must stay: deleting it anyway leaves that image attached to a
 * category that no longer exists, out of reach of every manager. The System
 * images admin and both editor image-manager copies share this loop.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ImageCategoryDeleteGuardTest extends TestCase
{
    /**
     * The three files that delete an image category with its images.
     *
     * @return array<string, array{string}>
     */
    public static function categoryDeleters(): array
    {
        return [
            'system admin' => ['/modules/system/admin/images/main.php'],
            'tinymce7'     => ['/class/xoopseditor/tinymce7/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php'],
            'tinymce5'     => ['/class/xoopseditor/tinymce5/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php'],
        ];
    }

    /**
     * A failed image delete must mark the batch, and the category delete must
     * be skipped (and reported) once it is marked.
     *
     * @param string $file path below XOOPS_ROOT_PATH
     * @return void
     */
    #[Test]
    #[DataProvider('categoryDeleters')]
    public function categoryIsKeptWhenAnImageCannotBeDeleted(string $file): void
    {
        $source = file_get_contents(XOOPS_ROOT_PATH . $file);
        self::assertIsString($source);

        self::assertMatchesRegularExpression(
            '/if \(!\$image_handler->delete\(\$images\[\$i\]\)\) \{\s*\$errors\[\] = sprintf\(_AM_SYSTEM_IMAGES_FAILDEL, \$i\);\s*\$imageDeleteFailed = true;/',
            $source,
            'a failed image delete must set $imageDeleteFailed'
        );
        self::assertSame(
            1,
            substr_count($source, '$imgcat_handler->delete($imagecategory)'),
            'the category is deleted in one place only'
        );
        self::assertMatchesRegularExpression(
            '/if \(\$imageDeleteFailed \|\| !\$imgcat_handler->delete\(\$imagecategory\)\) \{\s*\$errors\[\] = sprintf\(_AM_SYSTEM_IMAGES_FAILDELCAT,/',
            $source,
            'the category delete must be skipped and reported after a failed image delete'
        );
    }
}
