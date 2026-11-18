<?php
/**
 * Tests for the language constants of the [img] text sanitizer extension
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.5
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * class/textsanitizer/image/image.php uses three _MSC_ constants in every [img]
 * replacement. A language pack without misc.php, or with one that lacks a
 * constant, must not turn [img] into an "Undefined constant" error.
 */
final class MytsImageLanguageFallbackTest extends TestCase
{
    /**
     * @param string $pack "missing" or "partial", see the fixture
     *
     * @return array<string, string|null>
     */
    private function constantsWith(string $pack): array
    {
        $output = shell_exec(
            escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/fixtures/myts-image-language.php') . ' ' . $pack
        );
        $constants = json_decode((string) $output, true);
        $this->assertIsArray($constants, 'the fixture did not return JSON: ' . $output);

        return $constants;
    }

    #[Test]
    public function aPackWithoutTheLanguageFileGetsTheEnglishTexts(): void
    {
        $this->assertSame(
            [
                '_MSC_RESIZED_IMAGE'       => 'Resized Image',
                '_MSC_CLICK_TO_OPEN_IMAGE' => 'Click to see original Image in a new window',
                '_MSC_ORIGINAL_IMAGE'      => 'Original Image',
            ],
            $this->constantsWith('missing')
        );
    }

    #[Test]
    public function aPackKeepsItsOwnTextAndGetsOnlyTheMissingOnes(): void
    {
        $constants = $this->constantsWith('partial');

        $this->assertSame('Verkleinertes Bild', $constants['_MSC_RESIZED_IMAGE']);
        $this->assertSame('Original Image', $constants['_MSC_ORIGINAL_IMAGE']);
    }

    #[Test]
    public function theDefaultsMatchTheEnglishLanguageFile(): void
    {
        // the bootstrap loaded language/english/misc.php, so these are the shipped texts
        $this->assertSame(_MSC_RESIZED_IMAGE, $this->constantsWith('missing')['_MSC_RESIZED_IMAGE']);
        $this->assertSame(_MSC_CLICK_TO_OPEN_IMAGE, $this->constantsWith('missing')['_MSC_CLICK_TO_OPEN_IMAGE']);
        $this->assertSame(_MSC_ORIGINAL_IMAGE, $this->constantsWith('missing')['_MSC_ORIGINAL_IMAGE']);
    }
}
