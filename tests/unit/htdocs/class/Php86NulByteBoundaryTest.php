<?php

declare(strict_types=1);

namespace xoopsclass;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

require_once XOOPS_ROOT_PATH . '/class/xoopskernel.php';
require_once XOOPS_ROOT_PATH . '/class/logger/filelogger.php';
require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * PHP 8.6 NUL-byte boundary pins for the parse_str() call sites.
 *
 * PHP 8.6 throws ValueError when the string PASSED TO parse_str() contains a
 * literal NUL. A percent-encoded "%00" is three ordinary characters at that
 * boundary; parse_str() decodes it into a NUL in the OUTPUT values. These
 * tests pin both halves on every supported PHP version, so the distinction
 * (settled by direct experiment during the 8.6 review) survives refactors:
 *
 *  - "%00" input parses without error everywhere, and the decoded NUL in the
 *    output round-trips through rawurlencode()/http_build_query() re-encoding;
 *  - a literal "\0" input silently TRUNCATED parsing at the NUL before 8.6
 *    (everything from the NUL onward was dropped) and throws ValueError from
 *    8.6 on. The call sites now guard it, so the result is the same on every
 *    version: buildUrl() and redactSessionId() re-escape it to "%00" and
 *    parse on (redaction no longer stops short of a session id after it), and
 *    xoops_rebuildQueryString() treats it as unparseable and returns ''.
 *
 * The stat-family half of the 8.6 NUL story is already pinned by
 * FileSafetyTest; testDecodedNulValueIsRejectedAtTheFileSafetyBoundary()
 * below adds only the connective tissue: a superglobal-shaped decoded value
 * carrying a NUL is refused before it can reach a filesystem call.
 */
final class Php86NulByteBoundaryTest extends TestCase
{
    // =========================================================================
    // xos_kernel_Xoops2::buildUrl() - class/xoopskernel.php
    // =========================================================================

    public function testBuildUrlRoundTripsPercentEncodedNul(): void
    {
        $kernel = new \xos_kernel_Xoops2();

        // parse_str() decodes %00 into a literal NUL in the value; buildUrl()
        // re-encodes with rawurlencode(), restoring %00. No error on any version.
        $this->assertSame(
            '/index.php?a=%00b&c=d',
            $kernel->buildUrl('/index.php?a=%00b&c=d')
        );
    }

    public function testBuildUrlMergesParamsOverPercentEncodedNulQuery(): void
    {
        $kernel = new \xos_kernel_Xoops2();

        $this->assertSame(
            '/index.php?a=%00b&x=1',
            $kernel->buildUrl('/index.php?a=%00b', ['x' => '1'])
        );
    }

    public function testBuildUrlReescapesALiteralNulByteInQuery(): void
    {
        $kernel = new \xos_kernel_Xoops2();

        // Same result as "%00" on every version: no ValueError on 8.6, no
        // truncation (which dropped 'c=d') before it.
        $this->assertSame('/index.php?a=%00b&c=d', $kernel->buildUrl("/index.php?a=\0b&c=d"));
    }

    // =========================================================================
    // xos_kernel_Xoops2::pathExists() - class/xoopskernel.php
    // =========================================================================

    /**
     * Its own process: the failure path reads the language constant
     * _XO_ER_FILENOTFOUND, which the bootstrap does not define.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPathExistsReportsANulBytePathAsMissing(): void
    {
        define('_XO_ER_FILENOTFOUND', 'File not found');
        $GLOBALS['xoopsLogger'] = new class {
            public array $errors = [];

            public function triggerError(...$args): void
            {
                $this->errors[] = $args;
            }
        };

        $this->assertFalse((new \xos_kernel_Xoops2())->pathExists(__FILE__ . "\0.php", E_USER_WARNING));
        $this->assertCount(1, $GLOBALS['xoopsLogger']->errors);
        $this->assertSame(__FILE__, (new \xos_kernel_Xoops2())->pathExists(__FILE__, E_USER_WARNING));
    }

    // =========================================================================
    // xoops_rebuildQueryString() - include/file_safety.php
    // =========================================================================

    public function testRebuildQueryStringTreatsALiteralNulByteAsUnparseable(): void
    {
        $this->assertSame('', \xoops_rebuildQueryString("a=1\0&b=2"));
        $this->assertSame('?a=%00&b=2', \xoops_rebuildQueryString('a=%00&b=2'));
    }

    // =========================================================================
    // XoopsFileLogger::redactSessionId() - class/logger/filelogger.php
    // (reaches parse_str() with the RAW request uri, not parse_url() output,
    //  so a literal NUL genuinely arrives at the 8.6 boundary here)
    // =========================================================================

    public function testRedactSessionIdHandlesPercentEncodedNulInSessionValue(): void
    {
        $result = $this->redact('/x.php?' . $this->sessionName() . '=abc%00def&q=1');

        $this->assertStringContainsString('sid%23redacted', $result);
        $this->assertStringContainsString('q=1', $result);
        $this->assertStringNotContainsString('abc', $result);
        $this->assertStringNotContainsString("\0", $result);
    }

    public function testRedactSessionIdReencodesPercentEncodedNulInOtherParam(): void
    {
        $result = $this->redact('/x.php?' . $this->sessionName() . '=zzz&note=%00hi');

        // The decoded NUL in 'note' is re-encoded by http_build_query(),
        // never emitted raw into the log line.
        $this->assertStringContainsString('sid%23redacted', $result);
        $this->assertStringContainsString('note=%00hi', $result);
        $this->assertStringNotContainsString("\0", $result);
    }

    public function testRedactSessionIdWithLiteralNulByte(): void
    {
        $result = $this->redact('/x.php?' . $this->sessionName() . "=abc\0def&q=1");

        // Re-escaped before parsing: no ValueError on 8.6, nothing dropped.
        $this->assertStringContainsString('sid%23redacted', $result);
        $this->assertStringContainsString('q=1', $result);
        $this->assertStringNotContainsString('abc', $result);
        $this->assertStringNotContainsString("\0", $result);
    }

    public function testRedactSessionIdReachesASessionIdAfterALiteralNulByte(): void
    {
        // Before the guard, parse_str() stopped at the NUL on PHP < 8.6, so a
        // session id after it reached the log unredacted.
        $result = $this->redact("/x.php?a=\0&" . $this->sessionName() . '=secret');

        $this->assertStringContainsString('sid%23redacted', $result);
        $this->assertStringNotContainsString('secret', $result);
    }

    // =========================================================================
    // Decoded superglobal value -> file-safety boundary (stat family)
    // =========================================================================

    public function testDecodedNulValueIsRejectedAtTheFileSafetyBoundary(): void
    {
        // This is exactly how PHP populates $_GET: percent-decoding turns
        // "%00" into a literal NUL inside the VALUE.
        parse_str('f=upload%00.png', $params);
        $this->assertSame("upload\0.png", $params['f']);

        // The guard collapses it before any stat/filesystem call can see it -
        // on 8.6 that call would be an uncaught ValueError.
        $this->assertSame('invalid-path', \xoops_safe_basename($params['f']));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function redact(string $uri): string
    {
        $logger = (new ReflectionClass(\XoopsFileLogger::class))->newInstanceWithoutConstructor();
        // No setAccessible(): a no-op since PHP 8.1 and deprecated in 8.5 -
        // reflection invokes non-public methods without it on every
        // supported runtime.
        $method = new ReflectionMethod(\XoopsFileLogger::class, 'redactSessionId');

        return (string) $method->invoke($logger, $uri);
    }

    private function sessionName(): string
    {
        $name = (string) session_name();

        return '' !== $name ? $name : 'PHPSESSID';
    }
}
