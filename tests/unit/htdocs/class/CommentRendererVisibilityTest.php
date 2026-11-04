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

namespace xoopsclass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The thread and nested views list a reply only when it is active (or the
 * viewer is an administrator), and a stored parent loop must not recurse
 * without end. An inactive reply used to be skipped by its parent while its
 * own replies were listed unchecked, so an inactive grandchild showed.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(\XoopsCommentRenderer::class)]
final class CommentRendererVisibilityTest extends TestCase
{
    private object $tpl;

    protected function setUp(): void
    {
        require_once XOOPS_ROOT_PATH . '/include/comment_constants.php';
        require_once XOOPS_ROOT_PATH . '/language/english/comment.php';
        require_once XOOPS_ROOT_PATH . '/kernel/comment.php';
        require_once XOOPS_ROOT_PATH . '/class/tree.php';
        require_once XOOPS_ROOT_PATH . '/class/commentrenderer.php';
        $this->tpl = new class {
            /** @var array<string, mixed> */
            public array $vars = [];

            public function assign(string $name, mixed $value): void
            {
                $this->vars[$name] = $value;
            }

            public function append(string $name, mixed $value): void
            {
                $this->vars[$name][] = $value;
            }
        };
    }

    /**
     * 1 active top; 2 hidden reply; 3 pending reply to 2; 4 active reply to 3.
     */
    private function hiddenChain(): array
    {
        return [
            self::comment(1, 0, XOOPS_COMMENT_ACTIVE),
            self::comment(2, 1, XOOPS_COMMENT_HIDDEN),
            self::comment(3, 2, XOOPS_COMMENT_PENDING),
            self::comment(4, 3, XOOPS_COMMENT_ACTIVE),
        ];
    }

    #[Test]
    public function threadViewListsNoInactiveReplyAtAnyDepth(): void
    {
        $this->renderer($this->hiddenChain())->renderThreadView(1, false);

        $this->assertSame([4], array_column($this->tpl->vars['comments'][0]['replies'], 'id'));
    }

    #[Test]
    public function nestViewListsNoInactiveReplyAtAnyDepth(): void
    {
        $this->renderer($this->hiddenChain())->renderNestView(1, false);

        $replies = $this->tpl->vars['comments'][0]['replies'];
        $this->assertSame([4], array_column($replies, 'id'));
        $this->assertStringNotContainsString('text 3', implode(' ', array_column($replies, 'text')));
    }

    #[Test]
    public function administratorsStillSeeEveryReply(): void
    {
        $this->renderer($this->hiddenChain())->renderNestView(1, true);

        $this->assertSame([2, 3, 4], array_column($this->tpl->vars['comments'][0]['replies'], 'id'));
    }

    #[Test]
    public function threadViewTerminatesOnStoredParentLoops(): void
    {
        // 8 and 9 are each other's parent; 5 is a hidden comment that is its own parent.
        $comments = [
            self::comment(8, 9, XOOPS_COMMENT_ACTIVE),
            self::comment(9, 8, XOOPS_COMMENT_ACTIVE),
            self::comment(5, 5, XOOPS_COMMENT_HIDDEN),
        ];
        $renderer = $this->renderer($comments);
        $renderer->renderThreadView(8, false, false);
        $renderer->renderThreadView(5, false, false);

        $this->assertCount(1, $this->tpl->vars['comments']);
        $this->assertSame([9], array_column($this->tpl->vars['comments'][0]['replies'], 'id'));
    }

    #[Test]
    public function nestViewTerminatesOnStoredParentLoops(): void
    {
        $comments = [
            self::comment(8, 9, XOOPS_COMMENT_ACTIVE),
            self::comment(9, 8, XOOPS_COMMENT_ACTIVE),
            self::comment(5, 5, XOOPS_COMMENT_HIDDEN),
        ];
        $renderer = $this->renderer($comments);
        $renderer->renderNestView(8, false);
        $renderer->renderNestView(5, false);

        $this->assertCount(1, $this->tpl->vars['comments']);
        $this->assertSame([9], array_column($this->tpl->vars['comments'][0]['replies'], 'id'));
    }

    private function renderer(array $comments): \XoopsCommentRenderer
    {
        $renderer = (new \ReflectionClass(\XoopsCommentRenderer::class))->newInstanceWithoutConstructor();
        $renderer->_tpl        = $this->tpl;
        $renderer->_useIcons   = false;
        $renderer->_statusText = [
            XOOPS_COMMENT_PENDING => 'pending',
            XOOPS_COMMENT_ACTIVE  => 'active',
            XOOPS_COMMENT_HIDDEN  => 'hidden',
        ];
        $renderer->setComments($comments);

        return $renderer;
    }

    private static function comment(int $id, int $pid, int $status): \XoopsComment
    {
        $comment = new \XoopsComment();
        $comment->assignVars([
            'com_id'     => $id,
            'com_pid'    => $pid,
            'com_rootid' => 1,
            'com_modid'  => 1,
            'com_itemid' => 1,
            'com_status' => $status,
            'com_title'  => "title $id",
            'com_text'   => "text $id",
            'com_user'   => 'guest',
            'com_uid'    => 0,
        ]);

        return $comment;
    }
}
