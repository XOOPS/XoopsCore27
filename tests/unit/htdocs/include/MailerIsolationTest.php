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

namespace include_;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Each message gets its own mailer, so a notification sent to one subscriber
 * never carries the recipients, tags or template of the one before it.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class MailerIsolationTest extends TestCase
{
    /**
     * include/functions.php cannot load beside tests/bootstrap.php (both declare
     * xoops_getHandler()), so the shipped xoops_getMailer() is sliced from the
     * source and declared under a test name.
     */
    public static function setUpBeforeClass(): void
    {
        if (function_exists('xoops_getMailer_under_test')) {
            return;
        }
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/include/functions.php');
        $start  = strpos($source, 'function xoops_getMailer()');
        self::assertNotFalse($start);
        $end = strpos($source, "\n}\n", $start);
        self::assertNotFalse($end);
        $code = substr($source, $start, $end + 2 - $start);
        eval(str_replace('function xoops_getMailer()', 'function xoops_getMailer_under_test()', $code));
        $GLOBALS['xoopsConfig']['language'] ??= 'english';
        // xoopsmailer.php may already have been loaded by an earlier test with a
        // stubbed $xoops->path(), which skips its own include of this class.
        require_once XOOPS_ROOT_PATH . '/class/mail/xoopsmultimailer.php';
    }

    #[Test]
    public function eachMessageStartsWithACleanMailer(): void
    {
        $first = \xoops_getMailer_under_test();
        $first->setToEmails('first@example.com');
        $first->assign('X_ITEM', 'first item');
        $first->setTemplate('first.tpl');
        $first->setHTML(true);

        $second = \xoops_getMailer_under_test();
        $second->setToEmails('second@example.com');

        self::assertNotSame($first, $second);
        self::assertSame(['second@example.com'], $second->toEmails);
        self::assertSame([], $second->assignedTags);
        self::assertSame('', $second->template);
        self::assertSame('text/plain', $second->multimailer->ContentType);
    }
}
