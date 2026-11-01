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
 * The TinyMCE emoticon plugins built the inserted <img> by concatenating the
 * smiley's stored description into quoted attributes, so a quote in it added
 * attributes (event handlers) to the editor content. The element is built
 * with editor.dom.createHTML(), which encodes attribute values. TinyMCE loads
 * the .min.js file, so both copies are checked.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class EmoticonInsertEncodingTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function plugins(): array
    {
        $cases = [];
        foreach (['5', '7'] as $v) {
            foreach (['plugin.js', 'plugin.min.js'] as $file) {
                $cases["tinymce{$v} {$file}"] = ["class/xoopseditor/tinymce{$v}/js/tinymce/plugins/xoopsemoticons/{$file}"];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('plugins')]
    public function theImageIsBuiltWithEncodedAttributes(string $path): void
    {
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/' . $path);
        $compact = (string) preg_replace('/\s+/', '', $source);

        self::assertStringContainsString(
            "insertContent(editor.dom.createHTML('img',{src:data.data.src,alt:data.data.title,title:data.data.title}))",
            $compact
        );
        self::assertStringNotContainsString("'<imgsrc=\"'+data.data.src", $compact);
    }
}
