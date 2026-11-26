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

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xoops\Upgrade\UpgradeControl;
use XoopsMySQLDatabase;

require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';

/**
 * The welcome form asks for a backup confirmation before the first patch runs.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UpgradeControlFormTest extends TestCase
{
    protected function setUp(): void
    {
        // The form's button text; defined per process, so no other test sees it.
        if (!defined('_CONTINUE')) {
            define('_CONTINUE', 'Continue');
        }
    }

    #[Test]
    public function aConfirmationLabelAddsARequiredCheckboxWithAnEscapedLabel(): void
    {
        $form = $this->control()->oneButtonContinueForm('index.php', ['action' => 'next'], 'Backup of <db> & files');

        self::assertStringContainsString('type="checkbox" name="confirmed" value="1" required>', $form);
        self::assertStringContainsString('Backup of &lt;db&gt; &amp; files', $form);
        self::assertLessThan(strpos($form, '<button'), strpos($form, 'name="confirmed"'));
    }

    #[Test]
    public function withoutALabelTheFormHasNoCheckbox(): void
    {
        self::assertStringNotContainsString('checkbox', $this->control()->oneButtonContinueForm());
    }

    private function control(): UpgradeControl
    {
        return new UpgradeControl($this->createMock(XoopsMySQLDatabase::class));
    }
}
