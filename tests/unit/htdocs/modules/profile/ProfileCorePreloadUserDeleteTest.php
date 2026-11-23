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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/criteria.php';
require_once XOOPS_ROOT_PATH . '/kernel/user.php';
require_once XOOPS_ROOT_PATH . '/modules/profile/preloads/core.php';

/**
 * XoopsMemberHandler::deleteUser() raises core.behavior.user.delete once an
 * account is gone; the profile module's listener removes the account's row
 * from profile_profile. Before it, only the module's own admin page did, and
 * the three other delete paths left the row behind (XoopsCore27 #359).
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class ProfileCorePreloadUserDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        ProfilePreloadWithFakeHandler::$handler = new FakeProfileRowHandler();
    }

    #[Test]
    public function xoopsPreloadRegistersTheListenerAndTheEventReachesIt(): void
    {
        // Let XoopsPreload build its registry from the module's preload file,
        // as it does for an active module, then raise the event through it.
        $preload  = \XoopsPreload::getInstance();
        $preloads = $preload->_preloads;
        $events   = $preload->_events;
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = [$level, $message];
            return true;
        });
        try {
            $preload->_preloads = [['module' => 'profile', 'file' => 'core']];
            $preload->_events   = [];
            $preload->setEvents();
            $registered = $preload->_events['corebehavioruserdelete'] ?? [];
            $preload->triggerEvent('core.behavior.user.delete', $this->user(10));
        } finally {
            restore_error_handler();
            $preload->_preloads = $preloads;
            $preload->_events   = $events;
        }

        self::assertSame([['class_name' => 'ProfileCorePreload', 'method' => 'eventCoreBehaviorUserDelete']], $registered);
        // The real listener ran: the test bootstrap has no profile handler, and it says so.
        self::assertSame([[E_USER_WARNING, 'User 10 deleted; its profile row could not be removed']], $warnings);
    }

    #[Test]
    public function theProfileRowOfTheDeletedAccountIsRemoved(): void
    {
        self::assertSame([], $this->warningsOf($this->user(10)));
        $criteria = ProfilePreloadWithFakeHandler::$handler->criteria;
        self::assertCount(1, $criteria);
        self::assertSame(['profile_id', '=', 10], [$criteria[0]->column, $criteria[0]->operator, (int) $criteria[0]->value]);
    }

    #[Test]
    public function anAccountWithoutAProfileRowIsNotAFailure(): void
    {
        ProfilePreloadWithFakeHandler::$handler->result = 0;
        self::assertSame([], $this->warningsOf($this->user(10)));
    }

    #[Test]
    public function anArgumentThatIsNoSavedUserIsIgnored(): void
    {
        foreach ([null, 10, [], new \stdClass(), $this->user(0)] as $argument) {
            self::assertSame([], $this->warningsOf($argument));
        }
        self::assertSame([], ProfilePreloadWithFakeHandler::$handler->criteria);
    }

    #[Test]
    public function aFailedRowDeleteIsReportedAndDoesNotThrow(): void
    {
        ProfilePreloadWithFakeHandler::$handler->result = false;
        self::assertSame(
            [[E_USER_WARNING, 'User 10 deleted; its profile row could not be removed']],
            $this->warningsOf($this->user(10)),
        );
    }

    #[Test]
    public function aHandlerThatThrowsIsReportedAndDoesNotThrow(): void
    {
        ProfilePreloadWithFakeHandler::$handler->failure = new \RuntimeException('connection lost');
        self::assertSame(
            [[E_USER_WARNING, 'User 10 deleted; its profile row could not be removed']],
            $this->warningsOf($this->user(10)),
        );
    }

    #[Test]
    public function aMissingHandlerIsReported(): void
    {
        ProfilePreloadWithFakeHandler::$handler = null;
        self::assertSame(
            [[E_USER_WARNING, 'User 10 deleted; its profile row could not be removed']],
            $this->warningsOf($this->user(10)),
        );
    }

    /**
     * Run the listener and return what it reported.
     *
     * @return list<array{int, string}>
     */
    private function warningsOf(mixed $argument): array
    {
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = [$level, $message];
            return true;
        });
        try {
            ProfilePreloadWithFakeHandler::eventCoreBehaviorUserDelete($argument);
        } finally {
            restore_error_handler();
        }

        return $warnings;
    }

    private function user(int $uid): \XoopsUser
    {
        $user = $this->createStub(\XoopsUser::class);
        $user->method('getVar')->willReturnCallback(static fn (string $key) => 'uid' === $key ? $uid : null);

        return $user;
    }
}

/**
 * The listener with a handler that records instead of querying.
 */
final class ProfilePreloadWithFakeHandler extends \ProfileCorePreload
{
    public static ?FakeProfileRowHandler $handler = null;

    protected static function profileHandler()
    {
        return self::$handler ?? false;
    }
}

/**
 * Stands in for ProfileProfileHandler: records the criteria of each delete
 * and returns, or throws, what the test set.
 */
final class FakeProfileRowHandler
{
    /** @var list<\Criteria> */
    public array $criteria = [];

    /** @var int|false what deleteAll() returns: affected rows, or false for a failed query */
    public int|false $result = 1;

    public ?\Throwable $failure = null;

    public function deleteAll(\CriteriaElement $criteria): int|false
    {
        $this->criteria[] = $criteria;
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return $this->result;
    }
}
