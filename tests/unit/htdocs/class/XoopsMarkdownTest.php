<?php

declare(strict_types=1);

/**
 * Regression tests for XoopsMarkdown and the MyTextSanitizer display path.
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

require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsmarkdown.php';


#[CoversClass(XoopsMarkdown::class)]
#[CoversClass(MyTextSanitizer::class)]
final class XoopsMarkdownTest extends TestCase
{
    private MyTextSanitizer $myts;

    protected function setUp(): void
    {
        // No constructor: skips extension/config loading, as tests/markdown.php does.
        $this->myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
        $this->myts->config = ['extensions' => []];
    }

    private function display(string $text): string
    {
        return $this->myts->displayTarea($text, 0, 0, 1, 1, 1);
    }

    #[Test]
    public function bracketedMarkupStaysEncodedWhenHtmlIsOff(): void
    {
        $out = $this->display('hi [<img src=x onerror=alert(1)>] and [&lt;b&gt;]');

        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringNotContainsString('<b>', $out);
        $this->assertStringContainsString('&lt;img', $out);
    }

    #[Test]
    public function quotedAttributesFromVisualEditorsStillDecode(): void
    {
        foreach (['[size="x-large"]big[/size]', '[size=&quot;x-large&quot;]big[/size]'] as $input) {
            $out = $this->display($input);
            $this->assertStringContainsString('x-large', $out, $input);
            $this->assertStringNotContainsString('[size', $out, $input);
            $this->assertStringNotContainsString('&quot;', $out, $input);
        }
    }

    #[Test]
    public function quotedAttributeValuesCannotCarryMarkup(): void
    {
        $out = $this->display('[color=&quot;red&lt;b&gt;&quot;]x[/color]');

        $this->assertStringNotContainsString('<b>', $out);
        $this->assertStringNotContainsString('<span', $out);
    }

    #[Test]
    public function markdownPlaceholderCannotBreakOutOfAGeneratedAttribute(): void
    {
        $out = $this->display("[url=[xoops:markdown=\"1\"]\n\" onmouseover=\"alert(1)//\n[/xoops:markdown]]hover[/url]");

        $this->assertStringNotContainsString('onmouseover="alert', $out);
        $this->assertStringNotContainsString('XOOPSMARKDOWN', $out);
        $this->assertDoesNotMatchRegularExpression('/<a [^>]*<p>/', $out);
    }

    #[Test]
    public function markdownPlaceholderCannotBreakOutOfAQuotedAttributeHoldingAGreaterThan(): void
    {
        $text = '<img title="a>' . XoopsMarkdown::wrap('[x](http://e.test)') . '">';
        $out  = $this->myts->displayTarea($text, 1, 0, 1, 1, 1);

        $this->assertStringNotContainsString('href=', $out);
        $this->assertStringNotContainsString('XOOPSMARKDOWN', $out);
    }

    #[Test]
    public function embeddedMarkdownInAQuotedReplyStillRenders(): void
    {
        $out = $this->display("[quote]\n" . XoopsMarkdown::wrap('**bold**') . "\n[/quote]\nreply");

        $this->assertStringContainsString('<strong>bold</strong>', $out);
        $this->assertStringNotContainsString('XOOPSMARKDOWN', $out);
    }

    #[Test]
    public function nestedMarkerDoesNotFatal(): void
    {
        $nested = "[xoops:markdown=\"1\"]\na [xoops:markdown=\"1\"] b\n[/xoops:markdown] tail";

        $this->assertIsString($this->display($nested));
        $this->assertIsString(XoopsMarkdown::editorSource('[quote]' . $nested . '[/quote]'));
    }

    #[Test]
    public function preparePostLeavesOtherFieldsAndUnchangedWhitespaceAlone(): void
    {
        $legacy = "legacy  \n";
        $post = [
            'message'  => $legacy,
            'password' => ' secret ',
            '_xoops_markdown' => ['message'],
            '_xoops_markdown_state' => [hash('sha256', 'message') => [
                'initial' => XoopsMarkdown::fingerprint($legacy),
                'marked'  => '0',
            ]],
            '_xoops_markdown_save' => '1',
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame($legacy, $post['message'], 'unchanged text must not be converted');
        $this->assertSame(' secret ', $post['password']);
    }

    #[Test]
    public function preparePostWrapsAnEditedFieldOnSave(): void
    {
        $post = [
            'message' => "# edited\n",
            '_xoops_markdown' => ['message'],
            '_xoops_markdown_state' => [hash('sha256', 'message') => [
                'initial' => XoopsMarkdown::fingerprint('original'),
                'marked'  => '0',
            ]],
            '_xoops_markdown_save' => '1',
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame("# edited\n", XoopsMarkdown::source($post['message']));
        $this->assertSame($post['message'], $request['message']);
    }

    #[Test]
    public function restoreWithNoPlaceholdersReturnsTheTextUnchanged(): void
    {
        $this->assertSame('<p>a</p> b', XoopsMarkdown::restore('<p>a</p> b', []));
    }

    #[Test]
    public function previewFindsTheDocumentByTrimmedTextToo(): void
    {
        $edited = "    indented code\n";
        $post = [
            'message' => $edited,
            '_xoops_markdown' => ['message'],
            '_xoops_markdown_state' => [hash('sha256', 'message') => [
                'initial' => XoopsMarkdown::fingerprint('original'),
                'marked'  => '0',
            ]],
            '_xoops_markdown_save' => '0',
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame($edited, $post['message'], 'a preview never wraps the submitted field');
        $this->assertSame($edited, XoopsMarkdown::source(XoopsMarkdown::previewSource($edited)));
        $this->assertSame($edited, XoopsMarkdown::source(XoopsMarkdown::previewSource(trim($edited))));
    }

    #[Test]
    public function aFieldsExactTextWinsOverAnotherFieldsTrimmedText(): void
    {
        $state = ['initial' => XoopsMarkdown::fingerprint('original'), 'marked' => '0'];
        foreach ([['a', 'b'], ['b', 'a']] as $order) {
            $post = [
                'a' => 'hello',
                'b' => 'hello
',
                '_xoops_markdown' => $order,
                '_xoops_markdown_state' => [hash('sha256', 'a') => $state, hash('sha256', 'b') => $state],
            ];
            $request = $post;

            XoopsMarkdown::preparePost($post, $request);

            $this->assertSame('hello', XoopsMarkdown::source(XoopsMarkdown::previewSource('hello')));
            $this->assertSame(
                'hello
', XoopsMarkdown::source(XoopsMarkdown::previewSource(
                'hello
'
            )));
        }
    }

    #[Test]
    public function aMalformedStateIsIgnored(): void
    {
        foreach (['bad', [hash('sha256', 'm') => 'bad']] as $state) {
            $post = ['m' => 'x', '_xoops_markdown' => ['m'], '_xoops_markdown_state' => $state];
            $request = $post;

            XoopsMarkdown::preparePost($post, $request);

            $this->assertSame('x', $post['m']);
        }
    }

    #[Test]
    public function aTrimmedTextSharedByTwoFieldsPreviewsAsItself(): void
    {
        $state = ['initial' => XoopsMarkdown::fingerprint('original'), 'marked' => '0'];
        $post = [
            'a' => '    **hello**',
            'b' => '**hello**
',
            '_xoops_markdown' => ['a', 'b'],
            '_xoops_markdown_state' => [hash('sha256', 'a') => $state, hash('sha256', 'b') => $state],
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame('**hello**', XoopsMarkdown::source(XoopsMarkdown::previewSource('**hello**')));
        $this->assertSame('    **hello**', XoopsMarkdown::source(XoopsMarkdown::previewSource('    **hello**')));
    }

    #[Test]
    public function renderFallsBackToEscapedTextWithoutParsedown(): void
    {
        // Parsedown is already loaded here, so render in a PHP whose trust path has no vendor tree.
        $code = 'define("XOOPS_ROOT_PATH", ' . var_export(XOOPS_ROOT_PATH, true) . ');'
              . 'define("XOOPS_TRUST_PATH", ' . var_export(sys_get_temp_dir() . '/no-vendor-' . getmypid(), true) . ');'
              . 'require XOOPS_ROOT_PATH . "/class/xoopsmarkdown.php";'
              . 'echo XoopsMarkdown::render("**<b>x</b>**");';
        $proc = proc_open([PHP_BINARY, '-d', 'display_errors=stdout', '-r', $code], [1 => ['pipe', 'w']], $pipes);
        $out  = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $status = proc_close($proc);

        $this->assertSame(0, $status, $out);
        $this->assertStringContainsString('Parsedown is not installed', $out);
        $this->assertStringContainsString('<pre>**&lt;b&gt;x&lt;/b&gt;**</pre>', $out);
    }

    #[Test]
    public function tabsExpandToParsedownsColumnStopsInOnePass(): void
    {
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true);

        // A tab after one multibyte character pads to column 4, as Parsedown does.
        $this->assertSame($parsedown->text("é   b\n\tcode"), XoopsMarkdown::render("é\tb\n\tcode"));
        $this->assertStringContainsString('<pre><code>code</code></pre>', XoopsMarkdown::render("\tcode"));

        // Parsedown alone rebuilds the line once per tab; one pass keeps this linear.
        $start = microtime(true);
        XoopsMarkdown::render('a' . str_repeat("\t", 40000) . 'b');
        $this->assertLessThan(5.0, microtime(true) - $start, 'tab expansion must not be quadratic');
    }

    #[Test]
    public function blockquotesNestNoDeeperThanTheCap(): void
    {
        $html = XoopsMarkdown::render(str_repeat('> ', XoopsMarkdown::MAX_QUOTE_DEPTH + 4) . 'deep');

        $this->assertSame(XoopsMarkdown::MAX_QUOTE_DEPTH, substr_count($html, '<blockquote>'));
        // The 17th container renders its content as text (its own marker is consumed).
        $this->assertMatchesRegularExpression('/<p>(?:&gt; ){3}deep<\/p>/', $html);
        $this->assertSame(3, substr_count(XoopsMarkdown::render('> > > x'), '<blockquote>'));
    }

    #[Test]
    public function theDepthCapSeesTheLinesParsedownWillSplit(): void
    {
        $run = str_repeat('> ', XoopsMarkdown::MAX_QUOTE_DEPTH + 4) . 'deep';
        foreach (["\r", "\r\n"] as $eol) {
            $html = XoopsMarkdown::render('x' . $eol . $run);
            $this->assertSame(XoopsMarkdown::MAX_QUOTE_DEPTH, substr_count($html, '<blockquote>'), bin2hex($eol));
        }
    }

    #[Test]
    public function theDepthCapCoversIndentedContinuationLines(): void
    {
        $run = str_repeat('> ', XoopsMarkdown::MAX_QUOTE_DEPTH + 4) . 'deep';
        // Inside a quote, Parsedown continues it from an indented marker line.
        $html = XoopsMarkdown::render("> top\n      " . $run);
        $this->assertLessThanOrEqual(XoopsMarkdown::MAX_QUOTE_DEPTH, substr_count($html, '<blockquote>'), 'in a quote');
        // After a paragraph, an indented marker line is a continuation line.
        $html = XoopsMarkdown::render("text\n      " . $run);
        $this->assertLessThanOrEqual(XoopsMarkdown::MAX_QUOTE_DEPTH, substr_count($html, '<blockquote>'), 'after a paragraph');
        // Each container strips its own prefix before the next marker is read.
        foreach (["> > top\n>     " . $run, "- top\n- " . $run, "- a\n  - " . $run, '1. ' . $run, '1) ' . $run] as $source) {
            $html = XoopsMarkdown::render($source);
            $this->assertLessThanOrEqual(XoopsMarkdown::MAX_QUOTE_DEPTH, substr_count($html, '<blockquote>'), $source);
        }
        // Indented code keeps its markers as text.
        $this->assertStringContainsString('<pre><code>&gt; &gt; code', XoopsMarkdown::render('    > > code'));
    }

    #[Test]
    public function theDepthCapHoldsAcrossLazyContinuationLines(): void
    {
        $run = str_repeat('> ', XoopsMarkdown::MAX_QUOTE_DEPTH + 4) . 'deep';
        // Any non-blank line continues an open quote, so each line could nest
        // inside the last; the cap is on the parser, not on a line.
        foreach (["> top\nlazy\n- " . $run, str_repeat('> ', XoopsMarkdown::MAX_QUOTE_DEPTH) . "top\n- " . $run] as $source) {
            $html = XoopsMarkdown::render($source);
            $this->assertLessThanOrEqual(XoopsMarkdown::MAX_QUOTE_DEPTH, substr_count($html, '<blockquote>'), $source);
            $this->assertStringContainsString('deep', $html);
        }
        // 5000 markers on one line stay bounded.
        $html = XoopsMarkdown::render(str_repeat('> ', 5000) . 'deep');
        $this->assertSame(XoopsMarkdown::MAX_QUOTE_DEPTH, substr_count($html, '<blockquote>'));
        $this->assertStringContainsString('deep', $html);
        // List items nest through the same recursion and count toward the cap.
        $lines = [];
        for ($i = 0; $i < 40; ++$i) {
            $lines[] = str_repeat('  ', $i) . '- item';
        }
        $html = XoopsMarkdown::render(implode("\n", $lines));
        // Parsedown 1.7 also emits sibling items for some of these lines, so
        // measure the deepest open <li>, not the tag count.
        $open = $deepest = 0;
        foreach (preg_split('#(</?li>)#', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $token) {
            if ($token === '<li>') {
                $deepest = max($deepest, ++$open);
            } elseif ($token === '</li>') {
                --$open;
            }
        }
        $this->assertSame(XoopsMarkdown::MAX_QUOTE_DEPTH, $deepest);
    }

    #[Test]
    public function preparePostSkipsAFieldThatExceedsTheRemainingBudget(): void
    {
        $half  = str_repeat('a', intdiv(XoopsMarkdown::MAX_PREPARED_BYTES, 2) + 1);
        $state = ['initial' => XoopsMarkdown::fingerprint('x'), 'marked' => '0'];
        $post  = [
            'a' => $half, 'b' => $half, 'c' => "# edited\n",
            '_xoops_markdown' => ['a', 'b', 'c'],
            '_xoops_markdown_state' => [
                hash('sha256', 'a') => $state,
                hash('sha256', 'b') => $state,
                hash('sha256', 'c') => $state,
            ],
            '_xoops_markdown_save' => '1',
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame(XoopsMarkdown::wrap($half), $post['a']);
        $this->assertSame($half, $post['b'], 'the second field exceeds the remaining budget');
        $this->assertSame(XoopsMarkdown::wrap("# edited\n"), $post['c'], 'a later field that fits is still processed');
    }

    #[Test]
    public function preparePostLeavesAFieldOverTheWholeBudgetAlone(): void
    {
        $big  = str_repeat('a', XoopsMarkdown::MAX_PREPARED_BYTES + 1);
        $post = [
            'big'     => $big,
            'message' => "# edited\n",
            '_xoops_markdown' => ['big', 'message', 'message'],
            '_xoops_markdown_state' => [
                hash('sha256', 'big')     => ['initial' => XoopsMarkdown::fingerprint('x'), 'marked' => '0'],
                hash('sha256', 'message') => ['initial' => XoopsMarkdown::fingerprint('x'), 'marked' => '0'],
            ],
            '_xoops_markdown_save' => '1',
        ];
        $request = $post;

        XoopsMarkdown::preparePost($post, $request);

        $this->assertSame($big, $post['big'], 'the field over budget is left alone');
        $this->assertSame(XoopsMarkdown::wrap("# edited\n"), $post['message'], 'a later field within budget is still processed');
    }
}
