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

namespace htdocs;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/htdocs/modules/system/SourceFileTestTrait.php';

/**
 * image.php bounds its work per request: the edited-image cache is keyed by
 * the parameters the script reads, in a fixed order, so unknown or reordered
 * query parameters share one entry instead of writing a new one each, and the
 * number of canvas filters is capped. imageCacheKey() is sliced from the
 * script and executed as written.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ImageCacheKeyTest extends TestCase
{
    use SourceFileTestTrait;

    protected function setUp(): void
    {
        $this->loadSourceFile('htdocs/image.php');
        defined('MAX_IMAGE_FILTERS') || define('MAX_IMAGE_FILTERS', 8);
        if (!function_exists('imageCacheKey')) {
            $start = strpos($this->sourceContent, 'function imageCacheKey(');
            self::assertNotFalse($start, 'imageCacheKey() missing');
            $end = strpos($this->sourceContent, "\n}\n", $start);
            self::assertNotFalse($end);
            eval(substr($this->sourceContent, $start, $end + 3 - $start));
        }
    }

    #[Test]
    public function unknownAndReorderedParametersShareOneCacheEntry(): void
    {
        $base = imageCacheKey(['id' => '3', 'width' => '100'], 'pic.png');

        self::assertSame($base, imageCacheKey(['width' => '100', 'id' => '3'], 'pic.png'));
        self::assertSame($base, imageCacheKey(['id' => '3', 'width' => '100', 'x' => 'anything', 'nocache' => '1'], 'pic.png'));
        self::assertStringStartsWith('editedimage_', $base);
        self::assertStringEndsWith('_pic.png', $base);
    }

    #[Test]
    public function differentEditsAndSourcesGetTheirOwnEntries(): void
    {
        $base = imageCacheKey(['id' => '3', 'width' => '100'], 'pic.png');

        self::assertNotSame($base, imageCacheKey(['id' => '3', 'width' => '200'], 'pic.png'));
        self::assertNotSame($base, imageCacheKey(['id' => '3', 'width' => '100', 'filter' => ['IMG_FILTER_NEGATE']], 'pic.png'));
        self::assertNotSame($base, imageCacheKey(['id' => '3', 'width' => '100'], 'other.png'));
    }

    #[Test]
    public function onlyTheSourceSelectorInUseAndTheAppliedFiltersCount(): void
    {
        $base = imageCacheKey(['id' => '3', 'width' => '100'], 'pic.png');
        self::assertSame($base, imageCacheKey(['id' => '3', 'width' => '100', 'src' => 'a.png', 'url' => 'http://x/b.png'], 'pic.png'));
        // Request::getInt('id') reads the first integer run: "0.1e2" is 0, so url/src still select.
        self::assertNotSame(
            imageCacheKey(['id' => '0.1e2', 'src' => '/a/pic.png'], 'pic.png'),
            imageCacheKey(['id' => '0.1e2', 'src' => '/b/pic.png'], 'pic.png')
        );
        self::assertSame($base, imageCacheKey(['id' => '3abc', 'width' => '100', 'src' => 'a.png'], 'pic.png'));
        $byUrl = imageCacheKey(['url' => 'http://x/b.png', 'width' => '100'], 'b.png');
        self::assertSame($byUrl, imageCacheKey(['url' => 'http://x/b.png', 'src' => 'other.png', 'width' => '100'], 'b.png'));
        self::assertNotSame($byUrl, imageCacheKey(['url' => 'http://x/c.png', 'width' => '100'], 'b.png'));

        $eight = array_fill(0, MAX_IMAGE_FILTERS, 'IMG_FILTER_NEGATE');
        $nine  = [...$eight, 'IMG_FILTER_GRAYSCALE'];
        self::assertSame(
            imageCacheKey(['id' => '3', 'filter' => $eight], 'pic.png'),
            imageCacheKey(['id' => '3', 'filter' => $nine], 'pic.png')
        );
    }

    #[Test]
    public function theScriptUsesTheKeyAndCapsTheFilterCount(): void
    {
        self::assertStringContainsString("imageCacheKey(Request::get('GET'), (string) \$imageFilename)", $this->sourceContent);
        self::assertStringNotContainsString("md5(\$_SERVER['REQUEST_URI'])", $this->sourceContent);
        self::assertStringContainsString("define('MAX_IMAGE_FILTERS', 8);", $this->sourceContent);
        self::assertStringContainsString('array_slice((array) $filter, 0, MAX_IMAGE_FILTERS)', $this->sourceContent);
    }
}
