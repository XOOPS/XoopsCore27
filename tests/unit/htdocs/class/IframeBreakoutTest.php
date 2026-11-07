<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for the iframe BBCode single-quote breakout (SECURITY.md M-7)
 * and for nested BBCode inside the generated attributes.
 *
 * The src capture must exclude both quote types and angle brackets so a ' cannot
 * break out of the single-quoted src='...' attribute it is rendered into, and no
 * capture may hold [ or ]: a later replacement or callback ([img], [mp3]) would
 * otherwise expand inside the attribute and bring its own quotes.
 *
 * @see \MytsIframe
 */
final class IframeBreakoutTest extends TestCase
{
    /** The pattern MytsIframe::load() registers. */
    private static function pattern(): string
    {
        require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';
        require_once XOOPS_ROOT_PATH . '/class/textsanitizer/iframe/iframe.php';
        $myts           = (new \ReflectionClass(\MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $myts->patterns = [];
        (new \ReflectionClass(\MytsIframe::class))->newInstanceWithoutConstructor()->load($myts);

        return $myts->patterns[0];
    }

    #[Test]
    public function benignIframeStillMatches(): void
    {
        $ok = preg_match(self::pattern(), '[iframe=300]https://example.com/embed[/iframe]', $m);
        self::assertSame(1, $ok);
        self::assertSame('https://example.com/embed', $m[3]);
    }

    #[Test]
    public function singleQuoteBreakoutNoLongerMatches(): void
    {
        // The ' breaks the [^"'<>] class, so the tag no longer matches the closing
        // [/iframe] and is left as inert text instead of becoming an iframe src.
        self::assertSame(0, preg_match(self::pattern(), "[iframe=300]https://x/' onload='alert(1)[/iframe]"));
    }

    #[Test]
    public function angleBracketInjectionNoLongerMatches(): void
    {
        self::assertSame(0, preg_match(self::pattern(), '[iframe=300]https://x/"><script>[/iframe]'));
    }

    #[Test]
    public function dangerousSchemeNoLongerMatches(): void
    {
        // javascript:/data: do not start with http(s):// or // → no match → inert text.
        self::assertSame(0, preg_match(self::pattern(), '[iframe=300]javascript:alert(1)[/iframe]'));
        self::assertSame(0, preg_match(self::pattern(), '[iframe=300]data:text/html,1[/iframe]'));
    }

    #[Test]
    public function protocolRelativeSrcStillMatches(): void
    {
        $ok = preg_match(self::pattern(), '[iframe=300]//example.com/embed[/iframe]', $m);
        self::assertSame(1, $ok);
        self::assertSame('//example.com/embed', $m[3]);
    }

    #[Test]
    public function nestedBbcodeInSrcOrHeightNoLongerMatches(): void
    {
        self::assertSame(0, preg_match(self::pattern(), '[iframe=300]https://x/[mp3]https://y/a.mp3[/mp3][/iframe]'));
        self::assertSame(0, preg_match(self::pattern(), '[iframe=300]https://x/[img]https://y/a.png[/img][/iframe]'));
        self::assertSame(0, preg_match(self::pattern(), '[iframe=[b]1[/b]]https://x/[/iframe]'));
    }
}
