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

namespace Tests\Unit\Include;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * Input that PHP 8.6 treats differently: a NUL byte in a file check throws
 * ValueError, and trim() also removes a form feed.
 *
 * - The System help page and XoopsModule::loadInfo() put request input into
 *   file checks; a NUL byte there is a missing page or module, not an error.
 * - The banner client login tries the password as typed, then the trimmed,
 *   markup-stripped form older saves stored, and the client form now stores
 *   the password as typed, like account passwords.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class Php86InputBoundaryTest extends TestCase
{
    use SourceFileTestTrait;

    private const HELP_GUARD = "if (!preg_match('/^[A-Za-z0-9_.-]+\\z/', \$page) || str_contains(\$page, '..')) {";

    /**
     * @return array<string, array{string, string}>
     */
    public static function helpPages(): array
    {
        return [
            'page name'       => ['help', 'help'],
            'section name'    => ['blocksadmin', 'blocksadmin'],
            'dotted name'     => ['readme.en', 'readme.en'],
            'NUL byte'        => ["help\0", ''],
            'parent segment'  => ['../../../mainfile', ''],
            'dot-dot name'    => ['..', ''],
            'path'            => ['en/help', ''],
            'empty'           => ['', ''],
        ];
    }

    #[Test]
    #[DataProvider('helpPages')]
    public function theHelpPageAcceptsOnlyAFileName(string $requested, string $expected): void
    {
        $this->loadSourceFile('htdocs/modules/system/help.php');
        $start = strpos($this->sourceContent, self::HELP_GUARD);
        self::assertNotFalse($start);
        $end = strpos($this->sourceContent, "\n}\n", $start);
        self::assertNotFalse($end);
        // The guard sits before the first use of $page.
        self::assertLessThan(strpos($this->sourceContent, 'system_adminVersion($page'), $start);

        $page = $requested;
        eval(substr($this->sourceContent, $start, $end - $start + 2));
        self::assertSame($expected, $page);
    }

    #[Test]
    public function loadInfoReportsANulByteDirnameAsMissing(): void
    {
        $module = new \XoopsModule();

        // On PHP 8.6 the file checks inside would throw ValueError.
        self::assertFalse($module->loadInfo("system\0", false));
        // Checked before basename(), which would reduce this to "system".
        self::assertFalse($module->loadInfo("bad\0/system", false));
        $this->expectOutputString('Module File Not Found!');
        self::assertFalse($module->loadInfo("system\0.php", true));
    }

    /**
     * @return array<string, array{string, list<string>, bool}>
     */
    public static function bannerLogins(): array
    {
        $typedHash  = password_hash(' pw-example ', PASSWORD_DEFAULT);
        $legacyHash = password_hash('pw-example', PASSWORD_DEFAULT);

        return [
            'hash of the password as typed' => [$typedHash, [' pw-example ', 'pw-example'], true],
            'hash of the legacy form'       => [$legacyHash, [' pw-example ', 'pw-example'], true],
            'legacy plaintext'              => ['pw-example', [' pw-example ', 'pw-example'], true],
            'wrong password'                => [$typedHash, ['other-example'], false],
            'no password'                   => [$typedHash, [], false],
        ];
    }

    /**
     * @param list<string> $candidates
     */
    #[Test]
    #[DataProvider('bannerLogins')]
    public function theBannerLoginAcceptsEitherStoredForm(string $storedHash, array $candidates, bool $expected): void
    {
        $this->loadSourceFile('htdocs/banners.php');
        self::assertStringContainsString("\$clean_passwords = xoops_login_posted_passwords('pass');", $this->sourceContent);
        self::assertStringNotContainsString("getText('pass'", $this->sourceContent);
        $start = strpos($this->sourceContent, '$authenticated = false;');
        // The refusal after the loop, not the loop's own "if (!$authenticated)".
        $end   = strpos($this->sourceContent, "if (!\$authenticated) {\n                redirect_header(", (int) $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $xoopsDB = new class {
            /** @var list<string> */
            public array $writes = [];

            public function exec(string $sql): bool
            {
                $this->writes[] = $sql;

                return true;
            }

            public function prefix(string $table): string
            {
                return 'xoops_' . $table;
            }

            public function quote(string $value): string
            {
                return "'" . $value . "'";
            }
        };
        $clean_passwords = $candidates;
        $clientId        = 3;
        $columnLength    = 255;
        $authenticated   = null;
        // The column-width helper is defined in banners.php; read a local
        // instead, so the test declares no function.
        $slice = substr($this->sourceContent, $start, $end - $start);
        self::assertStringContainsString('bannerClientPasswdColumnLength($xoopsDB)', $slice);
        eval(str_replace('bannerClientPasswdColumnLength($xoopsDB)', '$columnLength', $slice));

        self::assertSame($expected, $authenticated);
        // A legacy plaintext password is upgraded to a hash once it matches.
        self::assertCount('pw-example' === $storedHash ? 1 : 0, $xoopsDB->writes);
    }

    #[Test]
    public function protectorCountsEveryPasswordTheLoginChecks(): void
    {
        // The login now authenticates the password as typed, so a password
        // that filters to '' (markup or whitespace only) reaches the check;
        // Protector must count it too, not skip it as an empty POST.
        // The function's own password read and its count gate are run here.
        $this->loadSourceFile('htdocs/xoops_lib/modules/protector/include/postcheck_functions.php');
        $read = self::slice($this->sourceContent, "\$pass = Request::getVar('pass',", "\$pass = is_string(\$pass) ? \$pass : '';");
        $gate = self::slice($this->sourceContent, "if (('' !== \$uname && '' !== \$pass)", "\n        }");
        self::assertLessThan(strpos($this->sourceContent, $gate), strpos($this->sourceContent, $read));

        $cases = [
            'plain'           => [['pass' => 'pw-example'], 1],
            'markup only'     => [['pass' => '<x>'], 1],
            'whitespace only' => [['pass' => '   '], 1],
            'form feed only'  => [['pass' => "\f"], 1],
            'empty'           => [['pass' => ''], 0],
            'missing'         => [[], 0],
            'array'           => [['pass' => ['pw-example']], 0],
        ];
        $savedPost = $_POST;
        try {
            foreach ($cases as $name => [$post, $expected]) {
                $_POST     = $post;
                $protector = new class {
                    public int $counted = 0;

                    public function check_brute_force(): void
                    {
                        ++$this->counted;
                    }
                };
                $uname           = 'alice';
                $autologin_uname = '';
                $autologin_pass  = '';
                eval("use Xmf\\Request;\n" . $read . "\n" . $gate);
                self::assertSame($expected, $protector->counted, $name);
            }
        } finally {
            $_POST = $savedPost;
        }
    }

    /**
     * @return array<string, array{mixed, ?string}> posted value, the password stored (null: none)
     */
    public static function bannerClientPasswords(): array
    {
        return [
            'boundary spaces' => [' pw-example ', ' pw-example '],
            'markup'          => ['a<b>c-example', 'a<b>c-example'],
            'spaces only'     => ['   ', '   '],
            'empty'           => ['', null],
            'array'           => [['pw-example'], null],
        ];
    }

    #[Test]
    #[DataProvider('bannerClientPasswords')]
    public function theBannerClientFormStoresThePasswordAsTyped(mixed $posted, ?string $stored): void
    {
        $hashedPasswd = $this->runBannerClientPasswordSave($posted);

        if (null === $stored) {
            self::assertNull($hashedPasswd);

            return;
        }
        self::assertIsString($hashedPasswd);
        self::assertTrue(password_verify($stored, $hashedPasswd));
        if (trim($stored) !== $stored && '' !== trim($stored)) {
            self::assertFalse(password_verify(trim($stored), $hashedPasswd), 'the trimmed form must not be what was stored');
        }
    }

    #[Test]
    public function theBannerClientFormRefusesAPasswordWithANulByte(): void
    {
        // password_hash() throws on one; the form reports it instead.
        $this->expectException(\RedirectHeaderException::class);
        $this->runBannerClientPasswordSave("pw-example\0");
    }

    /**
     * Run the client form's password read, up to and including the hash.
     */
    private function runBannerClientPasswordSave(mixed $posted): ?string
    {
        $this->loadSourceFile('htdocs/modules/system/admin/banners/main.php');
        $block = self::slice($this->sourceContent, "\$newPasswd = Request::getVar('passwd',", '$hashedPasswd = password_hash($newPasswd, PASSWORD_DEFAULT);') . "\n}";

        $savedPost    = $_POST;
        $_POST        = ['passwd' => $posted];
        $hashedPasswd = null;
        try {
            eval("use Xmf\\Request;\n" . $block);
        } finally {
            $_POST = $savedPost;
        }

        return $hashedPasswd;
    }

    private static function slice(string $source, string $from, string $through): string
    {
        $start = strpos($source, $from);
        self::assertNotFalse($start, "marker not found: $from");
        $end = strpos($source, $through, $start);
        self::assertNotFalse($end, "end marker not found: $through");

        return substr($source, $start, $end - $start + \strlen($through));
    }
}
