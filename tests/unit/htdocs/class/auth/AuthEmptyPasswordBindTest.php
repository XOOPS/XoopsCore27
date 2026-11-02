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

namespace Tests\Unit\ClassDir\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__, 2) . '/modules/system/SourceFileTestTrait.php';

/**
 * The directory adapters ran stripslashes() on the password after the login
 * form had checked it was not empty: the password "\" became "", and an
 * empty password is an anonymous bind that many directories accept, so the
 * bind "proved" a password nobody knew. The adapters bind with the password
 * as submitted and refuse an empty one before the user bind.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class AuthEmptyPasswordBindTest extends TestCase
{
    use SourceFileTestTrait;

    /**
     * @return array<string, array{string, string}>
     */
    public static function adapters(): array
    {
        return [
            'ldap' => ['htdocs/class/auth/auth_ldap.php', 'ldap_bind($this->_ds, $userDN, '],
            'ads'  => ['htdocs/class/auth/auth_ads.php', 'ldap_bind($this->_ds, $userUPN, '],
        ];
    }

    #[Test]
    #[DataProvider('adapters')]
    public function emptyPasswordIsRefusedBeforeTheUserBind(string $path, string $userBind): void
    {
        $this->loadSourceFile($path);

        $bind  = strpos($this->sourceContent, $userBind);
        $line  = substr($this->sourceContent, (int) $bind, strpos($this->sourceContent, ';', (int) $bind) - (int) $bind);
        self::assertStringNotContainsString('stripslashes', $line, 'the password is bound as submitted');
        $guard = strpos($this->sourceContent, "if ('' === (string) \$pwd) {");
        self::assertNotFalse($bind, 'user bind present');
        self::assertNotFalse($guard, 'empty-password guard present');
        self::assertLessThan($bind, $guard, 'the guard runs before the user bind');
        self::assertStringContainsString('return false;', substr($this->sourceContent, $guard, $bind - $guard));
    }
}
