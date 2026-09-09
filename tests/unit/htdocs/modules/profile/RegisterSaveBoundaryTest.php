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

namespace Tests\Unit\Profile;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/system/SourceFileTestTrait.php';

/**
 * The registration step number comes from the client. Identity validation
 * (uniqueness, password rules, agreement, captcha) ran only inside the
 * step-1 branch, while the save branch inserted for any later step, so a
 * request claiming step 2 created an account without any of it. The save
 * branch must now require that step 1 passed in this session and validate
 * the identity values again before inserting.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class RegisterSaveBoundaryTest extends TestCase
{
    use SourceFileTestTrait;

    protected function setUp(): void
    {
        $this->loadSourceFile('htdocs/modules/profile/register.php');
    }

    #[Test]
    public function stepOneRecordsItsOutcomeAndStepZeroResetsIt(): void
    {
        $stepOne = $this->between("if (\$current_step == 1) {", '// If the last step required SAVE');
        self::assertStringContainsString("\$_SESSION['profile_register_validated'] = ('' === \$stop);", $stepOne);

        $stepZero = $this->between("if (\$current_step == 0) {", '} else {');
        self::assertStringContainsString("\$_SESSION['profile_register_validated'] = false;", $stepZero);
    }

    #[Test]
    public function newUserIsValidatedAgainAtTheSaveBoundary(): void
    {
        $save = $this->between("\$isNew = \$newuser->isNew();", 'insertUser($newuser)');

        self::assertStringContainsString("empty(\$_SESSION['profile_register_validated'])", $save);
        self::assertStringContainsString('XoopsUserUtility::validate($newuser, $pass, $vpass)', $save);
    }

    #[Test]
    public function insertIsSkippedWhenTheSaveBoundaryValidationFailed(): void
    {
        $save = $this->between("\$isNew = \$newuser->isNew();", '$profile_handler->insert($profile)');

        self::assertMatchesRegularExpression(
            "/if \\('' !== \\\$stop\\) \\{.*?\\} elseif \\(!\\\$member_handler->insertUser\\(\\\$newuser\\)\\)/s",
            $save
        );
    }

    #[Test]
    public function stepOneRecordIsConsumedByASuccessfulInsert(): void
    {
        // A record left behind by a finished or abandoned flow must not
        // authorise a second insert from the same session.
        $afterInsert = $this->between("\$_SESSION['profile_register_uid'] = \$newuser->getVar('uid');", 'if (!empty($stop) || isset($steps[$current_step])) {');

        self::assertStringContainsString("\$_SESSION['profile_register_validated'] = false;", $afterInsert);
    }

    #[Test]
    public function passwordIsCarriedBetweenStepsUnfiltered(): void
    {
        // Other fields are tag-stripped and trimmed on the way into the
        // session; the password is hashed and validated as typed at the save
        // step, so a filtered copy would be rejected or hashed wrongly.
        $merge = $this->between('$postfields = [];', "if (\$current_step == 0) {");

        self::assertStringContainsString("('pass' === \$fieldname || 'vpass' === \$fieldname)", $merge);
        self::assertStringContainsString(
            "Request::getVar(\$fieldname, '', 'POST', 'string', Request::MASK_ALLOW_RAW | Request::MASK_NO_TRIM)",
            $merge
        );
    }

    private function between(string $from, string $to): string
    {
        $start = strpos($this->sourceContent, $from);
        self::assertNotFalse($start, "start marker not found: $from");
        $end = strpos($this->sourceContent, $to, $start);
        self::assertNotFalse($end, "end marker not found after start: $to");

        return substr($this->sourceContent, $start, $end - $start);
    }
}
