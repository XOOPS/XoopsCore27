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
        // written. Protector's one atomic writer (private temporary file,
        // target mode kept, renamed over it; ProtectorTest) does the write.
        $source = self::source('xoops_lib/modules/protector/admin/center.php');
        self::assertStringContainsString('if (!$protector->write_file_group1ips($group1_ips)) {', $source);
        self::assertStringNotContainsString('fopen(', $source, 'center.php writes no file itself');
        self::assertStringNotContainsString('ftruncate(', $source);

        $protector = self::source('xoops_lib/modules/protector/class/protector.php');
        self::assertStringContainsString(
            'return static::writeFileAtomic(static::get_filepath4group1ips(), serialize(array_unique($ips)) . "\n", false);',
            $protector
        );
    }

    #[Test]
    public function theInstallerCookieIsSecureOverHttps(): void
    {
        $source = self::source('install/include/common.inc.php');
        self::assertStringNotContainsString("'secure'   => false,", $source);
        self::assertStringContainsString("'secure'   => (!empty(\$_SERVER['HTTPS']) && 'off' !== strtolower((string) \$_SERVER['HTTPS']))", $source);
        // A session first seen over HTTPS is rotated once after it starts,
        // so a cookie issued over HTTP is replaced by a Secure one.
        $start  = strpos($source, 'session_start();');
        $rotate = strpos($source, 'if (session_regenerate_id(true)) {');
        self::assertNotFalse($start);
        self::assertNotFalse($rotate, 'the marker is set only when the rotation succeeded');
        self::assertLessThan($rotate, $start);
        self::assertStringContainsString("\$needsSecure = \$options['secure'] && empty(\$_SESSION['installer_secure_cookie']);", $source);
        // Every POST (each step that takes credentials) gets a new id, over HTTP or
        // HTTPS alike, and a POST whose id could not be replaced stops there.
        self::assertStringContainsString("\$isPost      = 'POST' === (\$_SERVER['REQUEST_METHOD'] ?? '');", $source);
        self::assertStringContainsString('if ($isPost || $needsSecure) {', $source);
        $fail = strpos($source, '} elseif ($isPost) {');
        self::assertNotFalse($fail);
        self::assertGreaterThan($rotate, $fail);
        $branch = substr($source, $fail, (int) strpos($source, "\n        }", $fail) - $fail);
        self::assertStringContainsString('http_response_code(500);', $branch);
        self::assertStringContainsString('exit(', $branch);
    }

    #[Test]
    public function aBannedVisitorExitsWithTheDebugOutputSwitchedOff(): void
    {
        $source = self::source('class/xoopssecurity.php');
        $method = substr($source, (int) strpos($source, 'public function checkBadips()'), 1600);
        $off    = strpos($method, "\$GLOBALS['xoopsLogger']->activated = false;");
        $exit   = strpos($method, 'exit();');
        self::assertNotFalse($off, 'the logger renders nothing once activated is false');
        self::assertNotFalse($exit);
        self::assertLessThan($exit, $off);
    }

    #[Test]
    public function theCustomSessionCookieIsNeverForcedInsecure(): void
    {
        $source = self::source('modules/system/admin/preferences/main.php');
        self::assertStringNotContainsString('XOOPS_COOKIE_DOMAIN, 0);', $source);
        // Secure follows the session handler's policy (session_cookie_secure
        // forces it on behind a TLS proxy that sends no X-Forwarded-Proto).
        self::assertStringContainsString("\$sessionSecure = (Request::getInt('session_cookie_secure', 0, 'POST') === 1 || session_get_cookie_params()['secure']) ? true : null;", $source);
        // SameSite is passed too: left out, the helper falls back to Lax even when Strict is configured.
        self::assertStringContainsString("xoops_setcookie(\$new_session_name, session_id(), time() + (60 * \$new_session_expire), '/', XOOPS_COOKIE_DOMAIN, \$sessionSecure, true, \$sessionSameSite);", $source);
        self::assertStringContainsString("\$sessionSameSite = Request::getString('session_cookie_samesite', '', 'POST');", $source);
        self::assertStringContainsString("session_get_cookie_params()['samesite']", $source);
    }

    #[Test]
    public function thePurifyFiltersHaveNoLengthCutOff(): void
    {
        foreach (['everyone', 'guest'] as $who) {
            $source = self::source("xoops_lib/modules/protector/filters_disabled/postcommon_post_htmlpurify4{$who}.php");
            self::assertStringNotContainsString('strlen($data) > 32', $source, $who);
            self::assertStringContainsString("str_contains((string) \$data, '<') ? call_user_func([\$this->purifier, \$this->method], \$data) : \$data;", $source, $who);
            // A password holding '<' is a credential, not markup. Only the
            // credential field names are skipped ("compass" is purified).
            $pattern = '/^(old|new|v)?pass(word)?\d*\z/i';
            self::assertStringContainsString("if (!preg_match('" . $pattern . "', (string) \$key)) {", $source, $who);
            foreach (['pass', 'pass2', 'vpass', 'password', 'oldpass', 'newpassword'] as $skipped) {
                self::assertSame(1, preg_match($pattern, $skipped), $skipped);
            }
            foreach (['compass', 'passage', 'pass_hint', "pass\n", 'title'] as $purified) {
                self::assertSame(0, preg_match($pattern, $purified), $purified);
            }
            self::assertStringNotContainsString('$_POST = $this->purify_recursive($_POST);', $source, $who);
        }
    }

    #[Test]
    public function theTextFilterKeepsTheSiteRulesAfterHtmlPurifier(): void
    {
        $source = self::source('class/textsanitizer/textfilter/textfilter.php');
        $purify = strpos($source, "\$text = (new \\HTMLPurifier(['Cache.DefinitionImpl' => null]))->purify((string) \$text);");
        self::assertNotFalse($purify, 'the bundled purifier result flows on');
        $config = strpos($source, '$config  = parent::loadConfig(__DIR__);');
        self::assertNotFalse($config);
        self::assertLessThan($config, $purify);
        self::assertStringNotContainsString('return (new \\HTMLPurifier', $source);
    }

    /**
     * The session handler expires a session by session.gc_maxlifetime, so
     * the configured custom lifetime is applied on an SSL-bridge POST too.
     */
    #[Test]
    public function theCustomSessionLifetimeAlsoAppliesToTheSslBridge(): void
    {
        $source   = self::source('include/common.php');
        $lifetime = strpos($source, "ini_set('session.gc_maxlifetime'");
        $bridge   = strpos($source, 'session_id($sslSessionId);');
        $start    = strpos($source, '$sessionStarted = session_start();');
        self::assertNotFalse($lifetime);
        self::assertNotFalse($bridge);
        self::assertNotFalse($start);
        self::assertLessThan($bridge, $lifetime);
        self::assertLessThan($start, $bridge);
        self::assertStringNotContainsString("} elseif (\$xoopsConfig['use_mysession']", $source, 'not an alternative to the bridge');
    }

    #[Test]
    public function provisioningKeepsOnlyTheExactName(): void
    {
        $source = self::source('class/auth/auth_provisionning.php');
        $start  = strpos($source, 'public function getXoopsUser($uname)');
        self::assertNotFalse($start);
        $body = substr($source, $start, (int) strpos($source, "\n    }\n", $start) - $start);
        // A quoted IN list keeps a digit-only name a string; the case-folded
        // exact match stops an accent-insensitive collation mapping a
        // directory name onto another local account.
        self::assertStringContainsString("new Criteria('uname', [(string) \$uname], 'IN')", $body);
        self::assertStringContainsString("\$fold(\$user->getVar('uname', 'n')) === \$fold(\$uname)", $body);
        $filter = strpos($body, 'array_filter(');
        $branch = strpos($body, 'if (count($getuser) == 1) {');
        self::assertNotFalse($filter);
        self::assertNotFalse($branch);
        self::assertLessThan($branch, $filter);
    }
}
