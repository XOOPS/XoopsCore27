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

namespace Xoops\Upgrade\Tests\Upgrade;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * login.php required webmaster-group membership, but index.php and
 * preflight.php let an already authenticated session through on
 * XoopsUser::isAdmin(), a module-level right that can be delegated. All three
 * entry points must use the one shared check defined in checkmainfile.php.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class WizardWebmasterGateTest extends TestCase
{
    private function source(string $file): string
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        self::assertNotFalse($content, "$file must be readable");

        return $content;
    }

    #[Test]
    public function theSharedCheckIsDefinedInTheBootstrapAndRequiresAnActiveWebmaster(): void
    {
        $bootstrap = $this->source('checkmainfile.php');

        self::assertStringContainsString('function xoops_upgrade_user_is_webmaster($user): bool', $bootstrap);
        $body = substr($bootstrap, strpos($bootstrap, 'function xoops_upgrade_user_is_webmaster'));
        $body = substr($body, 0, strpos($body, '// we have what we need so continue'));
        self::assertStringContainsString("(int) \$user->getVar('level') <= 0", $body);
        self::assertStringContainsString("in_array((int) XOOPS_GROUP_ADMIN, array_map('intval', \$groups), true)", $body);
    }

    #[Test]
    public function everyEntryPointUsesTheSharedCheckAndNotIsAdmin(): void
    {
        foreach (['index.php', 'preflight.php', 'login.php'] as $file) {
            $source = $this->source($file);
            self::assertStringContainsString('xoops_upgrade_user_is_webmaster(', $source, $file);
            self::assertStringNotContainsString('->isAdmin()', $source, "$file must not gate on module-level admin rights");
        }
    }
}
