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
 * BBCode arguments copied into HTML attributes.
 *
 * The decoder runs its patterns one after another over the same string, so a
 * later tag inside an argument an earlier tag put into an attribute (a [font]
 * inside a [url=...], an [mp3] inside an [img]) was rewritten inside that
 * attribute and broke out of it. An argument bound for an attribute never
 * holds [ or ]. Image URLs are http(s):// or a scheme-less path, so the
 * images-off link cannot carry javascript:, and a font name cannot rebuild a
 * CSS delimiter from an entity or a CSS escape.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';

#[CoversClass(MyTextSanitizer::class)]
final class MyTextSanitizerAttributeArgumentsTest extends TestCase
{
    private function display(string $text, int $image = 1): string
    {
        $myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $myts->path_basic  = XOOPS_ROOT_PATH . '/class/textsanitizer';
        $myts->path_config = XOOPS_VAR_PATH . '/configs/textsanitizer';
        $myts->path_plugin = XOOPS_ROOT_PATH . '/Frameworks/textsanitizer';
        $myts->config      = ['extensions' => ['image' => 1, 'mp3' => 1]];

        return $myts->displayTarea($text, 0, 0, 1, $image, 1);
    }

    /**
     * Every attribute value in the output, as the browser would read it.
     *
     * @return list<array{string, string}> [name, value]
     */
    private static function attributes(string $html): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true); // process-wide: restored below
        try {
            $doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $found = [];
        foreach ((new DOMXPath($doc))->query('//@*') as $attr) {
            $found[] = [$attr->nodeName, $attr->nodeValue];
        }

        return $found;
    }

    private static function assertNoHandlerOrScript(string $html): void
    {
        $bad = array_filter(
            self::attributes($html),
            static fn (array $a): bool => str_starts_with(strtolower($a[0]), 'on')
                || false !== stripos($a[1], 'javascript:')
        );
        self::assertSame([], array_values($bad), "event attribute or script URL in: {$html}");
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function breakouts(): array
    {
        return [
            'font inside url href' => ['[url=http://x.test/[font= a onmouseover=alert(1) b]y[/font]]t[/url]', 1],
            'font inside siteurl'  => ['[siteurl=a[font= x onmouseover=alert(1) ]y[/font]]t[/siteurl]', 1],
            'mp3 inside img src'   => ['[img]http://x.test/a[mp3] onerror=alert(1) x=[/mp3].png[/img]', 1],
            'mp3 inside img link'  => ['[img]http://x.test/a[mp3] onmouseover=alert(1) x=[/mp3].png[/img]', 0],
            'javascript img link'  => ['[img]javascript:location=name[/img]', 0],
            'javascript img src'   => ['[img]javascript:location=name[/img]', 1],
            'javascript aligned'   => ['[img align=left]javascript:location=name[/img]', 0],
            'vbscript img link'    => ['[img width=10]vbscript:msgbox[/img]', 0],
        ];
    }

    #[Test]
    #[DataProvider('breakouts')]
    public function anAttributeArgumentCannotOpenAnotherAttribute(string $source, int $image): void
    {
        self::assertNoHandlerOrScript($this->display($source, $image));
    }

    #[Test]
    public function fontNamesCannotRebuildACssDelimiter(): void
    {
        foreach (['[font=Arial&#59color:red]x[/font]', '[font=Arial\3b color:red]x[/font]'] as $source) {
            $out = $this->display($source);
            self::assertStringNotContainsString('font-family', $out, $source);
        }
        self::assertStringContainsString(
            '<span style="font-family: Times New Roman;">x</span>',
            $this->display('[font=Times New Roman]x[/font]')
        );
    }

    #[Test]
    public function ordinaryLinksAndImagesStillRender(): void
    {
        $out = $this->display('[url=https://x.test/a?b=1]t[/url] [img]https://x.test/i.png[/img] [img]uploads/i.png[/img]');
        self::assertStringContainsString('<a href="https://x.test/a?b=1" rel="noopener external" title="">t</a>', $out);
        self::assertStringContainsString("src='https://x.test/i.png'", $out);
        self::assertStringContainsString("src='uploads/i.png'", $out);

        $links = $this->display('[img]https://x.test/i.png[/img]', 0);
        self::assertStringContainsString('<a href="https://x.test/i.png" rel="external">https://x.test/i.png</a>', $links);

        // Schemes are case-insensitive.
        self::assertStringContainsString("src='HTTPS://x.test/i.png'", $this->display('[img]HTTPS://x.test/i.png[/img]'));

        // The image-manager form carries a caption, not a URL: a colon is fine.
        $caption = $this->display('[img id=2]Photo: sunset[/img]');
        self::assertStringContainsString('image.php?id=2', $caption);
        self::assertStringContainsString('title="Photo: sunset"', $caption);
    }

    #[Test]
    public function theClickableImageKeepsItsUrlOutOfScript(): void
    {
        // A javascript: URL is percent-decoded before it runs, so %22 closed
        // the loadImage("...") string. The URL is a plain href now.
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/class/textsanitizer/image/image.php');
        self::assertStringNotContainsString('javascript:loadImage', $source);
        self::assertSame(4, substr_count($source, "onclick='loadImage(this.getAttribute(\\\"href\\\")); return false;'"));
    }
}
