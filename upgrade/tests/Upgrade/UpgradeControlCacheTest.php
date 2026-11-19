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
use Xoops\Upgrade\PatchStatus;
use Xoops\Upgrade\UpgradeControl;
use XoopsMySQLDatabase;

/**
 * Cache cleaning moved out of the 2.5.11 / 2.7.0 patch tasks onto the
 * controller so a missing session flag cannot re-queue those patches.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class UpgradeControlCacheTest extends TestCase
{
    #[Test]
    public function cleanCachesIsANoOpWhenSystemMaintenanceIsNotOnDisk(): void
    {
        require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';
        $control = new UpgradeControl($this->createMock(XoopsMySQLDatabase::class));
        // The upgrade test bootstrap sets XOOPS_ROOT_PATH to upgrade/tests,
        // which has no modules/system/class/maintenance.php.
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_USER_WARNING);
        try {
            self::assertFalse($control->cleanCaches());
        } finally {
            restore_error_handler();
        }
        self::assertCount(1, $warnings);
        // Basename only: the warning must not reveal the server layout.
        self::assertStringContainsString('maintenance.php', $warnings[0]);
        self::assertStringNotContainsString(XOOPS_ROOT_PATH, $warnings[0]);
    }

    #[Test]
    public function cachesAreClearedAgainOnlyWhenTheLastPatchIsApplied(): void
    {
        require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';
        $control = $this->getMockBuilder(UpgradeControl::class)
            ->setConstructorArgs([$this->createMock(XoopsMySQLDatabase::class)])
            ->onlyMethods(['cleanCaches'])
            ->getMock();
        $control->upgradeQueue = ['upd_a' => $this->pendingPatch(), 'upd_b' => $this->pendingPatch()];

        // Pages and caches written while the patches ran must not reach the
        // System module update, so the final apply clears them once more.
        $control->expects(self::once())->method('cleanCaches')->willReturnCallback(
            static function () use ($control): bool {
                self::assertSame(0, $control->countUpgradeQueue(), 'caches cleared before the last patch');

                return true;
            },
        );

        $control->markApplied('upd_a');
        self::assertSame(1, $control->countUpgradeQueue());
        $control->markApplied('upd_b');
        self::assertSame(0, $control->countUpgradeQueue());
    }

    private function pendingPatch(): PatchStatus
    {
        $status          = (new \ReflectionClass(PatchStatus::class))->newInstanceWithoutConstructor();
        $status->applied = false;

        return $status;
    }
}
