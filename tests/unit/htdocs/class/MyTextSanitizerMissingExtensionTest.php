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
 * MyTextSanitizer with an extension that cannot be loaded.
 *
 * A site upgraded from 2.5.x keeps its saved textsanitizer config, which can still
 * enable 'flash' (removed in 2.7.0). executeExtension() used to call load() on the
 * false that loadExtension() returned, a TypeError on every page showing BBCode.
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

#[CoversClass(MyTextSanitizer::class)]
final class MyTextSanitizerMissingExtensionTest extends TestCase
{
    private string $emptyDir;

    /** @var list<string> */
    private array $warnings = [];

    protected function setUp(): void
    {
        $this->emptyDir = sys_get_temp_dir() . '/myts-missing-' . bin2hex(random_bytes(6));
        mkdir($this->emptyDir . '/broken', 0777, true);
        // A file is present but declares no MytsBroken class.
        file_put_contents($this->emptyDir . '/broken/broken.php', "<?php\n");
        $this->warnings = [];
    }

    protected function tearDown(): void
    {
        if (is_file($this->emptyDir . '/broken/broken.php')) {
            unlink($this->emptyDir . '/broken/broken.php');
        }
        foreach ([$this->emptyDir . '/broken', $this->emptyDir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    /** A sanitizer whose extension directories hold nothing loadable. */
    private function sanitizer(array $extensions = []): MyTextSanitizer
    {
        $myts = new MyTextSanitizer();
        $myts->path_basic  = $this->emptyDir;
        $myts->path_plugin = $this->emptyDir . '/none';
        $myts->config['extensions'] = $extensions;

        return $myts;
    }

    /**
     * Run $fn with warnings captured, and restore every handler the call left behind
     * (displayTarea() can reach XoopsLogger, which installs its own on first use).
     */
    private function capture(callable $fn): mixed
    {
        $errorBefore = set_error_handler(null);
        restore_error_handler();
        $exceptionBefore = set_exception_handler(null);
        restore_exception_handler();

        set_error_handler(function (int $no, string $msg): bool {
            $this->warnings[] = $msg;

            return true;
        }, E_USER_WARNING);
        try {
            return $fn();
        } finally {
            for ($i = 0; $i < 16; ++$i) {
                $current = set_error_handler(null);
                restore_error_handler();
                if ($current === $errorBefore) {
                    break;
                }
                restore_error_handler();
            }
            for ($i = 0; $i < 16; ++$i) {
                $current = set_exception_handler(null);
                restore_exception_handler();
                if ($current === $exceptionBefore) {
                    break;
                }
                restore_exception_handler();
            }
        }
    }

    #[Test]
    public function aMissingExtensionWarnsAndReturnsFalse(): void
    {
        $myts = $this->sanitizer();

        self::assertFalse($this->capture(fn() => $myts->executeExtension('flash')));
        self::assertSame(["Text sanitizer extension 'flash' is not available"], $this->warnings);
    }

    #[Test]
    public function anExtensionFileWithoutItsClassWarnsAndReturnsFalse(): void
    {
        $myts = $this->sanitizer();

        self::assertFalse($this->capture(fn() => $myts->executeExtension('broken')));
        self::assertContains("Text sanitizer extension 'broken' is not available", $this->warnings);
    }

    #[Test]
    public function aValidExtensionStillRuns(): void
    {
        $myts = new MyTextSanitizer();
        $myts->path_basic = XOOPS_ROOT_PATH . '/class/textsanitizer';

        $out = $this->capture(fn() => $myts->executeExtension('syntaxhighlight', 'echo 1;', 'php'));

        self::assertIsString($out);
        self::assertStringContainsString('echo', $out);
        self::assertSame([], $this->warnings);
    }

    #[Test]
    public function anEnabledRetiredFlashExtensionNoLongerBreaksRendering(): void
    {
        $myts = $this->sanitizer(['flash' => 1]);

        $out = $this->capture(fn() => $myts->displayTarea('[b]bold[/b] [flash=10,10]x.swf[/flash]', 0, 0));

        self::assertStringContainsString('<strong>bold</strong>', $out);
        self::assertStringNotContainsStringIgnoringCase('<object', $out);
        self::assertStringNotContainsStringIgnoringCase('<embed', $out);
        self::assertContains("Text sanitizer extension 'flash' is not available", $this->warnings);
    }

    #[Test]
    public function aCodeBlockWithoutTheHighlighterShowsLiteralTextWithHtmlOff(): void
    {
        $myts = $this->sanitizer();
        $src  = "[code]<b>x</b> & \"q\" 'a' &lt;\nline2[/code]";

        $out = $this->capture(fn() => $myts->displayTarea($src, 0, 0));

        self::assertStringContainsString('<div class="xoopsCode"><pre>', $out);
        self::assertStringNotContainsString('<b>', $out);
        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $out);
        self::assertStringContainsString('&quot;q&quot;', $out);
        self::assertStringContainsString('&lt;<br />line2</pre></div>', $out);
        self::assertStringNotContainsString('&amp;lt;', $out);
        self::assertStringNotContainsString('&amp;amp;', $out);
    }

    #[Test]
    public function aCodeBlockWithoutTheHighlighterShowsLiteralTextWithHtmlOn(): void
    {
        $myts = $this->sanitizer();
        $src  = "[code]<script>alert(1)</script> &amp; \"q\"[/code]";

        $out = $this->capture(fn() => $myts->displayTarea($src, 1, 0));

        self::assertStringNotContainsString('<script>', $out);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $out);
        self::assertStringContainsString('&amp;', $out);
        self::assertStringNotContainsString('&amp;amp;', $out);
    }

    #[Test]
    public function textFilterThrowsANamedExceptionWhenItsExtensionIsMissing(): void
    {
        $myts = $this->sanitizer();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Text sanitizer extension 'textfilter' is not available");
        $this->capture(fn() => $myts->textFilter('<p>x</p>', true));
    }

    #[Test]
    public function censorStringThrowsANamedExceptionWhenItsExtensionIsMissing(): void
    {
        $myts = $this->sanitizer();
        $text = 'hello';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Text sanitizer extension 'censor' is not available");
        $this->capture(fn() => $myts->censorString($text));
    }
}
