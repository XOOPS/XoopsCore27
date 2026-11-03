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

namespace modulesprofile;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Profile fields must never read credential or account-control columns: not
 * through search (including a request made only of forbidden columns), and not
 * through a field defined on such a column by an older install.
 *
 * @see \ProfileProfileHandler::search()
 * @see \ProfileField::getOutputValue()
 *
 * @category  Xoops
 * @package   Profile
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ProfileCredentialFieldTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!isset($GLOBALS['xoopsLogger'])) {
            $GLOBALS['xoopsLogger'] = \XoopsLogger::getInstance();
        }
        require_once XOOPS_ROOT_PATH . '/modules/profile/class/field.php';
        require_once XOOPS_ROOT_PATH . '/modules/profile/class/profile.php';
    }

    /** Run search() and return the SELECT list it sent. */
    private static function selectFor(array $searchvars): string
    {
        $db = new class extends \XoopsTestStubDatabase {
            public string $sql = '';

            public function query(string $sql, ?int $limit = null, ?int $start = null)
            {
                $this->sql = $sql;

                return false;
            }
        };
        $handler            = (new \ReflectionClass(\ProfileProfileHandler::class))->newInstanceWithoutConstructor();
        $handler->db        = $db;
        $handler->table     = 'x_profile_profile';
        $handler->_fHandler = (new \ReflectionClass(\ProfileFieldHandler::class))->newInstanceWithoutConstructor();

        self::assertSame([[], [], 0], $handler->search(new \CriteriaCompo(), $searchvars));

        return (string) strstr($db->sql, ' FROM ', true);
    }

    #[Test]
    public function aRequestForForbiddenColumnsOnlyNeverFallsBackToEveryColumn(): void
    {
        $select = self::selectFor(['pass', 'actkey']);

        self::assertStringNotContainsString('u.*', $select);
        self::assertStringNotContainsString('pass', $select);
        self::assertStringNotContainsString('actkey', $select);
        self::assertSame('SELECT u.uid, u.uname, u.email, u.user_viewemail', $select);
    }

    #[Test]
    public function aMixedRequestKeepsTheAllowedColumnsOnly(): void
    {
        $select = self::selectFor(['pass', 'user_from', 'level', 'favourite_color']);

        self::assertStringNotContainsString('pass', $select);
        self::assertStringNotContainsString('u.level', $select);
        self::assertStringContainsString('u.user_from', $select);
        self::assertStringContainsString('p.favourite_color', $select);
    }

    #[Test]
    public function noRequestedColumnsKeepsTheExistingSelectAll(): void
    {
        self::assertSame('SELECT u.*, p.*', self::selectFor([]));
    }

    /** @return array<string, array{string}> */
    public static function credentialNames(): array
    {
        return ['pass' => ['pass'], 'actkey' => ['actkey'], 'upper case' => ['PASS']];
    }

    #[Test]
    #[DataProvider('credentialNames')]
    public function aLegacyFieldOnACredentialColumnShowsNothing(string $name): void
    {
        $field = new \ProfileField();
        $field->setVar('field_name', $name);
        $field->setVar('field_type', 'textbox');
        self::assertTrue($field->isCredential());

        $user = $this->createMock(\XoopsUser::class);
        $user->expects($this->never())->method('getVar');
        $profile = $this->createMock(\ProfileProfile::class);
        $profile->expects($this->never())->method('getVar');

        self::assertSame('', $field->getOutputValue($user, $profile));
    }

    #[Test]
    public function ordinaryFieldsAreNotCredentials(): void
    {
        $field = new \ProfileField();
        foreach (['name', 'user_from', 'email', 'password_hint'] as $name) {
            $field->setVar('field_name', $name);
            self::assertFalse($field->isCredential(), $name);
        }
    }

    #[Test]
    public function optionLabelsAreTextWhereverAFormShowsThem(): void
    {
        require_once XOOPS_ROOT_PATH . '/modules/profile/include/forms.php';
        $options = ['a' => '<img src=x onerror=alert(1)>', 'b' => 'Fish & Chips', 'c' => '"quoted"'];
        self::assertSame(
            ['a' => '&lt;img src=x onerror=alert(1)&gt;', 'b' => 'Fish &amp; Chips', 'c' => '&quot;quoted&quot;'],
            profile_encodeOptionLabels($options)
        );

        $forms = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/profile/include/forms.php');
        self::assertSame(2, substr_count($forms, "profile_encodeOptionLabels(\$field->getVar('field_options'))"), 'both default selectors');
        self::assertStringContainsString("htmlspecialchars(\$options[\$key] . \"[{\$key}]\", ENT_QUOTES | ENT_HTML5, 'UTF-8')", $forms);

        $field = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/profile/class/field.php');
        self::assertStringContainsString(
            "\$optval = htmlspecialchars((string) (defined(\$options[\$key]) ? constant(\$options[\$key]) : \$options[\$key]), ENT_QUOTES | ENT_HTML5, 'UTF-8');",
            $field,
            'the member-facing edit element'
        );

        $save = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/profile/admin/field.php');
        self::assertStringContainsString("!is_array(\$option) || !is_scalar(\$option['key'] ?? '') || !is_scalar(\$option['value'] ?? null)", $save);
        self::assertStringContainsString("array_filter(Request::getArray('field_options', [], 'POST'), 'is_scalar')", $save);
    }

    #[Test]
    public function newFieldsCannotUseAccountControlColumns(): void
    {
        $handler = (new \ReflectionClass(\ProfileFieldHandler::class))->newInstanceWithoutConstructor();
        self::assertSame(['uid', 'uname', 'email', 'pass', 'level', 'actkey'], $handler->getAccountControlVars());

        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/modules/profile/admin/field.php');
        self::assertMatchesRegularExpression(
            '/in_array\(strtolower\(\$fieldName\), \$profilefield_handler->getAccountControlVars\(\), true\)\)\s*\{\s*redirect_header/',
            $source
        );
    }
}
