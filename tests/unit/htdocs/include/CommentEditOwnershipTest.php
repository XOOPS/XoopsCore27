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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * comment_edit.php loaded any comment id from the query string and rendered
 * its text, email and URL into the edit form with no check on who was
 * asking, while the save and delete paths enforce owner-or-module-admin. The
 * edit form must apply the same rule before any field is read.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class CommentEditOwnershipTest extends TestCase
{
    use SourceFileTestTrait;

    #[Test]
    public function editFormRequiresOwnerOrModuleAdminBeforeReadingTheComment(): void
    {
        $this->loadSourceFile('htdocs/include/comment_edit.php');

        $load = strpos($this->sourceContent, '$comment         = $comment_handler->get($com_id);');
        self::assertNotFalse($load);
        $firstRead = strpos($this->sourceContent, "\$comment->getVar('dohtml')", $load);
        self::assertNotFalse($firstRead);
        $guard = substr($this->sourceContent, $load, $firstRead - $load);

        self::assertStringContainsString('is_object($comment) && is_object($xoopsUser)', $guard);
        // Authorisation is against the module the comment belongs to, not the
        // module whose page carries the request, and the system comment
        // moderator right the save path honours is accepted here too.
        self::assertStringContainsString("\$xoopsUser->isAdmin((int) \$comment->getVar('com_modid'))", $guard);
        self::assertStringNotContainsString("isAdmin(\$xoopsModule->getVar('mid'))", $guard);
        self::assertStringContainsString("checkRight('system_admin', XOOPS_SYSTEM_COMMENT, \$xoopsUser->getGroups())", $guard);
        self::assertStringContainsString("(int) \$comment->getVar('com_uid') === (int) \$xoopsUser->getVar('uid')", $guard);
        self::assertStringContainsString("(int) \$xoopsUser->getVar('uid') > 0", $guard, 'anonymous comments have no owner');
        self::assertStringContainsString('redirect_header(XOOPS_URL', $guard);
        self::assertStringContainsString('_NOPERM', $guard);
    }
}
