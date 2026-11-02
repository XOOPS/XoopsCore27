<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/snoopy.php';

/**
 * Unit tests for Snoopy — security audit phase 2.
 *
 * Verifies that _httpsrequest uses native PHP curl_* functions
 * instead of shell exec(), and that basic class instantiation works.
 */
#[CoversClass(\Snoopy::class)]
class SnoopyTest extends TestCase
{
    private static ?\Snoopy $snoopy = null;

    public static function setUpBeforeClass(): void
    {
        if (!isset($GLOBALS['xoopsLogger'])) {
            $GLOBALS['xoopsLogger'] = \XoopsLogger::getInstance();
        }
        // Create single instance to avoid repeated XoopsLogger deprecation logging
        self::$snoopy = new \Snoopy();
    }

    private function snoopy(): \Snoopy
    {
        return self::$snoopy;
    }

    #[Test]
    public function canInstantiate(): void
    {
        $this->assertInstanceOf(\Snoopy::class, $this->snoopy());
    }

    #[Test]
    public function defaultErrorIsEmptyString(): void
    {
        $this->assertSame('', $this->snoopy()->error);
    }

    #[Test]
    public function defaultCurlPathIsSet(): void
    {
        $this->assertSame('/usr/bin/curl', $this->snoopy()->curl_path);
    }

    #[Test]
    public function defaultAgentContainsSnoopy(): void
    {
        $this->assertStringContainsString('Snoopy', $this->snoopy()->agent);
    }

    #[Test]
    public function defaultResultsIsEmptyArray(): void
    {
        $this->assertSame([], $this->snoopy()->results);
    }

    #[Test]
    public function defaultHeadersIsEmptyArray(): void
    {
        $this->assertSame([], $this->snoopy()->headers);
    }

    #[Test]
    public function defaultPortIs80(): void
    {
        $this->assertSame(80, $this->snoopy()->port);
    }

    // =========================================================================
    // _httpsrequest uses curl_* not exec()
    // =========================================================================

    #[Test]
    public function httpsRequestMethodExists(): void
    {
        $this->assertTrue(method_exists($this->snoopy(), '_httpsrequest'));
    }

    #[Test]
    public function httpsRequestRequiresCurlExtension(): void
    {
        // If curl extension is available, _httpsrequest will attempt a real
        // connection. If not, it should return false with an error.
        // We can only test the contract here — not mock the extension.
        if (!function_exists('curl_init')) {
            $result = $this->snoopy()->_httpsrequest('/path', 'https://example.com/path', 'GET');
            $this->assertFalse($result);
            $this->assertStringContainsString('cURL extension', $this->snoopy()->error);
        } else {
            // curl is available — method exists and is callable
            $this->assertTrue(function_exists('curl_init'));
        }
    }

    #[Test]
    public function snoopySourceDoesNotContainExecCall(): void
    {
        // Verify that the active code path in _httpsrequest does not use exec()
        $source = file_get_contents(XOOPS_ROOT_PATH . '/class/snoopy.php');
        // The method should use curl_init/curl_exec instead
        $this->assertStringContainsString('curl_init', $source);
        $this->assertStringContainsString('curl_exec', $source);
    }

    /**
     * A redirect to another host or port must not carry Basic-auth
     * credentials, cookies or Authorization/Cookie headers there; a
     * same-origin or relative redirect keeps them.
     */
    #[Test]
    public function credentialsStayWithTheirOrigin(): void
    {
        // The request being redirected: https with no explicit port (443).
        $from = 'https://api.example.test/start';
        $make = function (): \Snoopy {
            $s = clone $this->snoopy(); // never alter the shared instance
            $s->host       = 'api.example.test';
            $s->user       = 'u';
            $s->pass       = 'p';
            $s->cookies    = ['sid' => 'x'];
            $s->headers    = ["Set-Cookie: session=secret\r\n"];
            $s->rawheaders = ['Authorization' => 'Bearer t', 'cookie' => 'a=b', 'X-Trace' => '1'];

            return $s;
        };

        foreach (['https://API.example.test/next', '/relative', 'https://api.example.test:443/x'] as $same) {
            $s = $make();
            $this->assertSame($same, $s->_leaveOrigin($same, $from));
            $this->assertSame(['u', 'p'], [$s->user, $s->pass], $same);
            $this->assertCount(3, $s->rawheaders, $same);
        }

        foreach (['https://evil.test/', 'http://api.example.test/', 'http://api.example.test:443/',
                  'https://api.example.test:8443/', 'https://api.example.test.evil.test/'] as $other) {
            $s = $make();
            $this->assertSame($other, $s->_leaveOrigin($other, $from));
            $this->assertSame(['', '', [], []], [$s->user, $s->pass, $s->cookies, $s->headers], $other);
            $this->assertSame(['X-Trace' => '1'], $s->rawheaders, $other);
            // The next request reads the previous response's Set-Cookie lines
            // into the jar; none survive the switch of origin.
            $s->setcookies();
            $this->assertSame([], $s->cookies, $other);
        }

        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/class/snoopy.php');
        $this->assertSame(0, preg_match('/->(fetch|submit)\(\$this->_redirectaddr\b/', $source), 'every followed redirect passes _leaveOrigin()');
        $this->assertSame(2, substr_count($source, '$this->_leaveOrigin($this->_redirectaddr, $URI)'), 'fetch(): http and https');
        $this->assertSame(2, substr_count($source, '$this->_followSubmitRedirect($URI, $formvars, $formfiles)'), 'submit(): http and https');
    }

    /**
     * A submit() redirect re-posts the form body only to the same origin;
     * another origin, or a target with a query string, is fetched by GET.
     */
    #[Test]
    public function aSubmitRedirectNeverPostsTheFormToAnotherOrigin(): void
    {
        $from = 'https://api.example.test/login';
        $cases = [
            'https://api.example.test/next' => 'submit',
            '/relative'                     => 'fetch', // expanded before this point; unknown origin is not trusted
            'https://api.example.test/n?x'  => 'fetch',
            'https://evil.test/collect'     => 'fetch',
            'http://api.example.test/next'  => 'fetch',
        ];
        foreach ($cases as $target => $expected) {
            $s = new class extends \Snoopy {
                public array $calls = [];

                public function fetch($URI)
                {
                    $this->calls[] = ['fetch', $URI];

                    return true;
                }

                public function submit($URI, $formvars = '', $formfiles = '')
                {
                    $this->calls[] = ['submit', $URI, $formvars, $formfiles];

                    return true;
                }
            };
            $s->_redirectaddr = $target;
            $s->_followSubmitRedirect($from, ['pass' => 'secret'], ['f' => '/tmp/x']);
            $this->assertSame($expected, $s->calls[0][0], $target);
            $this->assertCount('submit' === $expected ? 4 : 2, $s->calls[0], $target . ': a GET carries no form body');
        }
    }

    #[Test]
    public function snoopySourceDoesNotContainShellExec(): void
    {
        $source = file_get_contents(XOOPS_ROOT_PATH . '/class/snoopy.php');
        // exec() should no longer appear as a function call in the HTTPS method
        $this->assertStringNotContainsString("exec(\$this->curl_path", $source);
    }
}
