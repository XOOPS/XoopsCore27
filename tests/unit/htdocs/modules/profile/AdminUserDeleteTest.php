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

namespace Tests\Unit\Modules\Profile;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RedirectHeaderException;

/**
 * The profile row of a deleted account is removed by the module's listener of
 * core.behavior.user.delete (see ProfileCorePreloadUserDeleteTest). The admin
 * page can be opened while the module is inactive and no listener is loaded,
 * so after a successful deleteUser() it runs that listener itself; it has no
 * row delete of its own. deleteUser() can refuse (its token delete can fail):
 * the page then says what failed and the account keeps its profile row.
 * The confirmed-delete branch of modules/profile/admin/user.php is sliced out
 * and executed with spies; each case runs in its own process because the
 * branch needs a language constant.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class AdminUserDeleteTest extends TestCase
{
    private const NS = 'Tests\\Unit\\Modules\\Profile\\DeleteSandbox';

    private const BRANCH_START = "            if (!\$handler->deleteUser(\$obj)) {";

    private const BRANCH_END = "        } else {\n            xoops_confirm(";

    protected function setUp(): void
    {
        defined('_PROFILE_AM_DELETEDSUCCESS') || define('_PROFILE_AM_DELETEDSUCCESS', 'Deleted %s');
        // One case runs without the constant, as a translated pack that
        // predates it would; each test is its own process, so this holds.
        if (!in_array($this->name(), ['theFailureMessageFallsBackWhenTheConstantIsUndefined', 'aBeta2PackWithTheOldConstantNameIsStillUsed'], true)) {
            defined('_PROFILE_AM_DELETE_FAILED') || define('_PROFILE_AM_DELETE_FAILED', 'Deleting %s failed');
        }
        $GLOBALS['userErrors'] = [];
        $GLOBALS['deleteOrderLog']       = [];
        $GLOBALS['deleteUserResult']     = true;
        $GLOBALS['profileCleanupThrows'] = false;
        $this->loadSandbox();
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aCleanupThatThrowsStillEndsInTheSuccessRedirect(): void
    {
        // the account is gone; the page must not end in the error page
        $GLOBALS['profileCleanupThrows'] = true;
        $out = $this->runBranch();
        self::assertInstanceOf(RedirectHeaderException::class, $out['redirect']);
        self::assertSame(['deleteUser:10', 'profileCleanup:10'], $GLOBALS['deleteOrderLog']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function theProfileRowIsCleanedAfterTheAccountAndSuccessRedirects(): void
    {
        $out = $this->runBranch();
        self::assertInstanceOf(RedirectHeaderException::class, $out['redirect']);
        self::assertStringContainsString('Deleted alice', $out['redirect']->getMessage());
        self::assertSame(['deleteUser:10', 'profileCleanup:10'], $GLOBALS['deleteOrderLog']);
        self::assertSame('', $out['echo']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aRefusedAccountDeleteKeepsTheProfileRow(): void
    {
        $GLOBALS['deleteUserResult'] = false;
        $out = $this->runBranch();
        self::assertNull($out['redirect']);
        self::assertSame(['deleteUser:10'], $GLOBALS['deleteOrderLog'], 'the profile row must not be touched');
        // deleteUser() leaves no error on the object when its token step fails
        self::assertSame('error:Deleting alice failed', $out['echo']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function thePageRemovesTheRowThroughTheListenerOnly(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 5) . '/htdocs/modules/profile/admin/user.php');
        // an inactive module's preload is not loaded by XoopsPreload
        self::assertStringContainsString("include_once dirname(__DIR__) . '/preloads/core.php';", $source);
        $start = strpos($source, "    case 'delete':");
        self::assertNotFalse($start);
        $case = substr($source, $start);
        self::assertSame(1, substr_count($case, '->deleteUser('));
        self::assertSame(1, substr_count($case, 'ProfileCorePreload::eventCoreBehaviorUserDelete($obj);'));
        self::assertStringNotContainsString('xoops_getModuleHandler(', $case, 'no second row delete beside the listener');
        self::assertStringNotContainsString('->delete(', $case, 'no second row delete beside the listener');
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function theFailureMessageFallsBackWhenTheConstantIsUndefined(): void
    {
        self::assertFalse(defined('_PROFILE_AM_DELETE_FAILED'));
        $GLOBALS['deleteUserResult'] = false;
        $out = $this->runBranch();
        self::assertNull($out['redirect']);
        self::assertSame('error:Deleting alice failed; the account was not removed', $out['echo']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aBeta2PackWithTheOldConstantNameIsStillUsed(): void
    {
        // 2.7.4-Beta2 shipped _PROFILE_AM_DELETEFAILED; a pack translated then keeps working.
        define('_PROFILE_AM_DELETEFAILED', 'Löschen von %s fehlgeschlagen');
        self::assertFalse(defined('_PROFILE_AM_DELETE_FAILED'));
        $GLOBALS['deleteUserResult'] = false;
        $out = $this->runBranch();
        self::assertSame('error:Löschen von alice fehlgeschlagen', $out['echo']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aRefusedAccountDeleteShowsTheObjectErrorsWhenThereAreAny(): void
    {
        $GLOBALS['deleteUserResult'] = false;
        $GLOBALS['userErrors']       = ['row locked'];
        $out = $this->runBranch();
        self::assertNull($out['redirect']);
        self::assertSame('error:row locked', $out['echo']);
    }

    /**
     * @return array{redirect: ?RedirectHeaderException, echo: string}
     */
    private function runBranch(): array
    {
        $fn = self::NS . '\\run_confirmed_delete';
        ob_start();
        $redirect = null;
        try {
            $fn();
        } catch (RedirectHeaderException $e) {
            $redirect = $e;
        } finally {
            $echo = (string) ob_get_clean();
        }

        return ['redirect' => $redirect, 'echo' => $echo];
    }

    private function loadSandbox(): void
    {
        if (function_exists(self::NS . '\\run_confirmed_delete')) {
            return;
        }
        $source = file_get_contents(dirname(__DIR__, 5) . '/htdocs/modules/profile/admin/user.php');
        self::assertNotFalse($source);
        $start = strpos($source, self::BRANCH_START);
        $end   = strpos($source, self::BRANCH_END, (int) $start);
        self::assertNotFalse($start, 'confirmed-delete branch start marker');
        self::assertNotFalse($end, 'confirmed-delete branch end marker');
        $branch = substr($source, $start, $end - $start);

        $stubs = <<<'PHP'
        namespace Tests\Unit\Modules\Profile\DeleteSandbox;

        function xoops_error(string|array $msg): void {
            echo 'error:' . (is_array($msg) ? implode(',', $msg) : $msg);
        }
        // the branch names the class unqualified, so this one stands in for the module's
        final class ProfileCorePreload {
            public static function eventCoreBehaviorUserDelete(object $user): void {
                $GLOBALS['deleteOrderLog'][] = 'profileCleanup:' . $user->getVar('uid');
                if ($GLOBALS['profileCleanupThrows']) {
                    throw new \RuntimeException('cleanup broke');
                }
            }
        }
        PHP;
        eval($stubs);

        // The branch reads $handler and $obj from the page; here they are the
        // function's locals. redirect_header() is the bootstrap's throwing stub.
        $wrapper = 'namespace ' . self::NS . ";\n"
            . "function run_confirmed_delete(): void {\n"
            . "    \$handler = new class { public function deleteUser(object \$u): bool { \$GLOBALS['deleteOrderLog'][] = 'deleteUser:' . \$u->getVar('uid'); return \$GLOBALS['deleteUserResult']; } };\n"
            . "    \$obj = new class { public function getVar(string \$k): mixed { return ['uid' => 10, 'uname' => 'alice', 'email' => 'a@example.org'][\$k] ?? null; } public function getErrors(): array { return \$GLOBALS['userErrors']; } public function getHtmlErrors(): string { return 'user-errors'; } };\n"
            . $branch . "\n}\n";
        eval($wrapper);
    }
}
