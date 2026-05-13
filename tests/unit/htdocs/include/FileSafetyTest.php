<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/include/file_safety.php';

/**
 * Coverage for the three side-effect-free file helpers in
 * htdocs/include/file_safety.php. The helpers are documented as
 * best-effort / non-propagating: on any failure they emit a single
 * E_USER_WARNING (or return early) and continue. These tests pin that
 * contract specifically against null-byte payloads, which trigger
 * ValueError in the underlying filesystem calls on PHP 8+ — the case
 * that motivated the explicit catch(\Throwable) wrappers and the
 * xoops_safe_basename() defensive shape.
 */
class FileSafetyTest extends TestCase
{
    public function testXoopsSafeBasenameReturnsPlaceholderForNullBytePath(): void
    {
        $this->assertSame(
            'invalid-path',
            xoops_safe_basename("bad\0path"),
            'null-byte payload must collapse to the fixed placeholder'
        );
    }

    public function testXoopsSafeBasenameNormalisesBackslashes(): void
    {
        $this->assertSame(
            'foo.png',
            xoops_safe_basename('avatars\\sub\\foo.png'),
            'Windows-style separators must be normalised before basename()'
        );
    }

    public function testXoopsSafeBasenamePassesThroughOrdinaryPath(): void
    {
        $this->assertSame('foo.png', xoops_safe_basename('avatars/foo.png'));
    }

    public function testXoopsChmodQuietlyDoesNotPropagateOnNullBytePath(): void
    {
        // chmod() raises ValueError on PHP 8+ when its $filename
        // argument contains "\0". xoops_chmod_quietly() must catch that
        // and report failure via its boolean return + a single
        // E_USER_WARNING, never letting the exception escape. Use a
        // local error handler instead of the @ operator to swallow the
        // warning — this codebase forbids @-suppression and we want to
        // assert the warning still fires.
        $captured = null;
        set_error_handler(static function (int $level, string $msg) use (&$captured): bool {
            if (E_USER_WARNING === $level) {
                $captured = $msg;
                return true;
            }
            return false;
        });

        try {
            $result = xoops_chmod_quietly("bad\0path", 0644, 'test');
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($result, 'chmod on a null-byte path must report failure');
        $this->assertNotNull($captured, 'helper must still emit one E_USER_WARNING on failure');
        $this->assertStringContainsString('invalid-path', (string) $captured);
    }

    public function testXoopsRemoveFileQuietlyDoesNotPropagateOnNullBytePath(): void
    {
        // The pre-check file_exists() / is_link() does not throw on a
        // "\0"-bearing path in PHP 8.2-8.4 (it returns false), so the
        // helper returns early without ever reaching unlink(). The
        // contract being tested is just "no exception escapes".
        xoops_remove_file_quietly("bad\0path");
        $this->assertTrue(true, 'xoops_remove_file_quietly returned without throwing');
    }

    public function testXoopsRemoveFileQuietlyIsNoOpForMissingPath(): void
    {
        // A non-existent path must NOT emit a warning — only paths that
        // still exist after a failed unlink() should. Wire up an error
        // handler to assert no E_USER_WARNING fires.
        $warningFired = false;
        set_error_handler(static function (int $level) use (&$warningFired): bool {
            if (E_USER_WARNING === $level) {
                $warningFired = true;
                return true;
            }
            return false;
        });

        try {
            xoops_remove_file_quietly(sys_get_temp_dir() . '/xoops_definitely_missing_' . uniqid() . '.tmp');
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($warningFired, 'no warning should fire for an already-absent path');
    }

    public function testXoopsFileLabelStripsXoopsRootPrefix(): void
    {
        // xoops_file_label() keeps install-relative context (unlike
        // xoops_safe_basename()) for atomic-write call sites that
        // benefit from knowing WHICH file failed. Spot-check the
        // strip-prefix branch and the basename fallback.
        $absUnderRoot = rtrim(XOOPS_ROOT_PATH, '/\\') . '/uploads/foo.png';
        $this->assertSame('uploads/foo.png', xoops_file_label($absUnderRoot));

        $absOutside = sys_get_temp_dir() . '/foo.png';
        $this->assertSame('foo.png', xoops_file_label($absOutside));
    }
}
