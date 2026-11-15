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
 * The editor image-manager plugin (one copy per TinyMCE version) used _MD_
 * message constants that no language file defines, so the category delete
 * confirmation and every delete-failure message died with an Error on PHP 8.
 * Every _MD_ / _AM_ constant the plugin uses must be defined in a language
 * file it has loaded: the global file, or the two System admin files it
 * loads itself.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ImageManagerPluginConstantsTest extends TestCase
{
    private const LANGUAGE_FILES = [
        '/language/english/global.php',
        '/modules/system/language/english/admin.php',
        '/modules/system/language/english/admin/images.php',
    ];

    /**
     * The two copies of the plugin, one per TinyMCE version.
     *
     * @return array<string, array{string}>
     */
    public static function pluginCopies(): array
    {
        return [
            'tinymce7' => ['/class/xoopseditor/tinymce7/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php'],
            'tinymce5' => ['/class/xoopseditor/tinymce5/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php'],
        ];
    }

    private static function read(string $relativePath): string
    {
        $source = file_get_contents(XOOPS_ROOT_PATH . $relativePath);
        self::assertIsString($source, $relativePath . ' must be readable');
        return $source;
    }

    /**
     * Every _MD_ / _AM_ constant the plugin uses is defined in a loaded file.
     *
     * @param string $plugin path below XOOPS_ROOT_PATH
     * @return void
     */
    #[Test]
    #[DataProvider('pluginCopies')]
    public function everyLanguageConstantUsedIsDefined(string $plugin): void
    {
        $source = self::read($plugin);
        self::assertStringContainsString("xoops_loadLanguage('admin', 'system')", $source);
        self::assertStringContainsString("xoops_loadLanguage('/admin/images', 'system')", $source);

        $defined = [];
        foreach (self::LANGUAGE_FILES as $file) {
            preg_match_all("/define\(\s*'(_[A-Z0-9_]+)'/", self::read($file), $m);
            $defined += array_flip($m[1]);
        }

        preg_match_all('/\b_(?:MD|AM)_[A-Z0-9_]+\b/', $source, $m);
        $used = array_unique($m[0]);
        self::assertNotEmpty($used);

        $undefined = array_values(array_filter($used, static fn (string $c): bool => !isset($defined[$c])));
        self::assertSame([], $undefined, 'constants used but defined in no loaded language file');
    }
}
