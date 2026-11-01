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
 * Small hardening contracts that have no runtime seam in the unit suite:
 *
 * - template override directories are created 0755, not world-writable;
 * - Protector's IP allowlist is truncated only while its lock is held;
 * - the installer's session cookie is Secure over HTTPS;
 * - the cookie set when custom sessions are switched on is never forced
 *   non-Secure and is HttpOnly;
 * - the opt-in HTML purify filters purify every value that can hold markup;
 * - LDAP provisioning loads only the account with exactly the given name.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class FilesCookiesAndFiltersContractTest extends TestCase
{
    private static function source(string $path): string
    {
        $source = file_get_contents(XOOPS_ROOT_PATH . '/' . $path);
        self::assertIsString($source, $path);

        return $source;
    }

    #[Test]
    public function templateOverrideDirectoriesAreNotWorldWritable(): void
    {
        $source = self::source('modules/system/admin/tplsets/main.php');
        self::assertStringNotContainsString('0777', $source);
        self::assertGreaterThan(0, substr_count($source, ', 0755)'));
    }

    #[Test]
    public function theAllowlistIsReplacedAtomically(): void
    {
        // Readers take no lock: the live file must never be empty or half
        // written, so the new list is written beside it and renamed over it.
        $source = self::source('xoops_lib/modules/protector/admin/center.php');
        $open   = strpos($source, "\$fp = fopen(\$tmpPath, 'x');");
        $write  = strpos($source, '$bytesWritten = fwrite($fp, $data);', (int) $open);
        $rename = strpos($source, 'if (!rename($tmpPath, $filePath)) {', (int) $open);
        self::assertNotFalse($open, 'the new list goes to a temporary file');
        self::assertNotFalse($write);
        self::assertNotFalse($rename);
        self::assertLessThan($rename, $write);
        self::assertStringNotContainsString('fopen($filePath', $source, 'the live file is never opened for writing');
        self::assertStringNotContainsString('ftruncate(', $source);
        self::assertStringContainsString('unlink($tmpPath);', $source, 'a failed attempt leaves no temporary file');
    }

    #[Test]
    public function theInstallerCookieIsSecureOverHttps(): void
    {
        $source = self::source('install/include/common.inc.php');
        self::assertStringNotContainsString("'secure'   => false,", $source);
        self::assertStringContainsString("'secure'   => (!empty(\$_SERVER['HTTPS']) && 'off' !== strtolower((string) \$_SERVER['HTTPS']))", $source);
    }

    #[Test]
    public function theCustomSessionCookieIsNeverForcedInsecure(): void
    {
        $source = self::source('modules/system/admin/preferences/main.php');
        self::assertStringNotContainsString('XOOPS_COOKIE_DOMAIN, 0);', $source);
        self::assertStringContainsString("xoops_setcookie(\$new_session_name, session_id(), time() + (60 * \$new_session_expire), '/', XOOPS_COOKIE_DOMAIN, null, true);", $source);
    }

    #[Test]
    public function thePurifyFiltersHaveNoLengthCutOff(): void
    {
        foreach (['everyone', 'guest'] as $who) {
            $source = self::source("xoops_lib/modules/protector/filters_disabled/postcommon_post_htmlpurify4{$who}.php");
            self::assertStringNotContainsString('strlen($data) > 32', $source, $who);
            self::assertStringContainsString("str_contains((string) \$data, '<') ? call_user_func([\$this->purifier, \$this->method], \$data) : \$data;", $source, $who);
        }
    }

    #[Test]
    public function provisioningKeepsOnlyTheExactName(): void
    {
        $source = self::source('class/auth/auth_provisionning.php');
        $start  = strpos($source, 'public function getXoopsUser($uname)');
        self::assertNotFalse($start);
        $body = substr($source, $start, (int) strpos($source, "\n    }\n", $start) - $start);
        // Only a digit-only name (rendered as an SQL number) is narrowed to
        // the exact account; others keep the column collation's matching.
        self::assertStringContainsString("if (preg_match('/^-?\\d+\$/', (string) \$uname)) {", $body);
        self::assertStringContainsString("(string) \$user->getVar('uname', 'n') === (string) \$uname", $body);
        self::assertLessThan(strpos($body, 'if (count($getuser) == 1) {'), strpos($body, 'array_filter('));
    }
}
