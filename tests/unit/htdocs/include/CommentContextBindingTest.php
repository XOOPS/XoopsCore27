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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';
require_once XOOPS_ROOT_PATH . '/include/comment_constants.php';

/**
 * Comment ids posted or linked by a visitor must stay inside the module and
 * item the request was authorized for:
 *  - comment_post.php takes a reply's root from its parent, and the parent
 *    must be a comment of the same module and item;
 *  - comment_reply.php quotes only a comment of the hosting module that is
 *    active, unless the visitor may moderate it;
 *  - comment_view.php URL-encodes extra parameters placed in thread links.
 * Each block is sliced from its file and executed as written; the bootstrap's
 * redirect_header() throws.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class CommentContextBindingTest extends TestCase
{
    use SourceFileTestTrait;

    private function slice(string $file, string $from, string $to, bool $includeTo = false): string
    {
        $this->loadSourceFile($file);
        $start = strpos($this->sourceContent, $from);
        self::assertNotFalse($start, "$file: start marker missing");
        $end = strpos($this->sourceContent, $to, $start);
        self::assertNotFalse($end, "$file: end marker missing");

        return substr($this->sourceContent, $start, $end - $start + ($includeTo ? strlen($to) : 0));
    }

    /**
     * @return array<string, array{int, mixed, ?int}> [posted pid, parent, expected root or null = refused]
     */
    public static function parents(): array
    {
        return [
            'top comment ignores posted root'     => [0, null, 0],
            'reply in same thread'                => [10, self::comment(10, 3, 9, 4), 4],
            'reply to a root without stored root' => [10, self::comment(10, 3, 9, 0), 10],
            'parent of another module'            => [10, self::comment(10, 4, 9, 4), null],
            'parent of another item'              => [10, self::comment(10, 3, 8, 4), null],
            'unknown parent'                      => [10, null, null],
            'fresh object for unknown id'         => [10, self::comment(0, 0, 0, 0), null],
            'pending parent, guest'               => [10, self::comment(10, 3, 9, 4, XOOPS_COMMENT_PENDING), null],
            'hidden parent, guest'                => [10, self::comment(10, 3, 9, 4, XOOPS_COMMENT_HIDDEN), null],
            'pending parent, module admin'        => [10, self::comment(10, 3, 9, 4, XOOPS_COMMENT_PENDING), 4, true],
        ];
    }

    #[Test]
    #[DataProvider('parents')]
    public function aReplyTakesItsRootFromAParentOfTheSameModuleAndItem(int $com_pid, mixed $parent, ?int $root, bool $moderator = false): void
    {
        $xoopsUser = $moderator ? self::moderator() : null;
        $code = $this->slice(
            'htdocs/include/comment_post.php',
            '$com_rootid = 0;',
            '$comment = $comment_handler->create();',
        );
        $comment_handler = new class($parent) {
            public function __construct(private mixed $parent)
            {
            }

            public function get(int $id): mixed
            {
                return $this->parent;
            }
        };
        $com_modid     = 3;
        $com_itemid    = 9;
        $com_rootid    = 77; // posted value, must never be used
        $com_mode      = 'flat';
        $com_order     = 0;
        $redirect_page = 'index.php?item';
        if (null === $root) {
            $this->expectException(\RedirectHeaderException::class);
        }
        eval($code);
        self::assertSame($root, $com_rootid);
    }

    #[Test]
    public function anEditKeepsTheStoredRoot(): void
    {
        $code = $this->slice(
            'htdocs/include/comment_post.php',
            "if (!is_object(\$comment) || (int) \$comment->getVar('com_modid') !== (int) \$com_modid) {",
            'if (is_object($xoopsUser)) {',
        );
        $comment    = self::comment(12, 3, 9, 4);
        $com_modid  = 3;
        $com_rootid = 0; // posted value would make the reply its own root
        eval($code);
        self::assertSame(4, $com_rootid);
    }

    /**
     * @return array<string, array{mixed, string, mixed, bool}> [comment, hosting dirname, user, allowed]
     */
    private static function moderator(): object
    {
        return new class {
            public function isAdmin(mixed $mid = null): bool
            {
                return true;
            }

            public function getGroups(): array
            {
                return [1];
            }
        };
    }

    public static function replyTargets(): array
    {
        $admin = self::moderator();

        return [
            'active comment of this module'    => [self::comment(10, 3, 9, 10), 'news', null, true],
            'active comment of another module' => [self::comment(10, 4, 9, 10), 'news', null, false],
            'system context, guest'            => [self::comment(10, 4, 9, 10), 'system', null, false],
            'system context, moderator'        => [self::comment(10, 4, 9, 10), 'system', $admin, true],
            'another module, moderator'        => [self::comment(10, 4, 9, 10), 'news', $admin, false],
            'pending comment, guest'           => [self::comment(10, 3, 9, 10, XOOPS_COMMENT_PENDING), 'news', null, false],
            'hidden comment, guest'            => [self::comment(10, 3, 9, 10, XOOPS_COMMENT_HIDDEN), 'news', null, false],
            'pending comment, module admin'    => [self::comment(10, 3, 9, 10, XOOPS_COMMENT_PENDING), 'news', $admin, true],
            'unknown comment'                  => [null, 'news', null, false],
            'fresh object for unknown id'      => [self::comment(0, 0, 0, 0), 'news', null, false],
        ];
    }

    #[Test]
    #[DataProvider('replyTargets')]
    public function theReplyPageQuotesOnlyAVisibleCommentOfThisModule(mixed $comment, string $dirname, mixed $xoopsUser, bool $allowed): void
    {
        $code = $this->slice(
            'htdocs/include/comment_reply.php',
            '$reply_allowed = is_object($comment)',
            'unset($reply_allowed, $reply_moderator);',
            true,
        );
        $xoopsModule = new class($dirname) {
            public function __construct(private string $dirname)
            {
            }

            public function getVar(string $key): mixed
            {
                return 'dirname' === $key ? $this->dirname : 3;
            }
        };
        if (!$allowed) {
            $this->expectException(\RedirectHeaderException::class);
        }
        eval($code);
        self::assertTrue($allowed);
    }

    #[Test]
    public function extraParametersInThreadLinksAreUrlEncoded(): void
    {
        $code = $this->slice(
            'htdocs/include/comment_view.php',
            "foreach (\$comment_config['extraParams'] as \$extra_param) {",
            '$comment_url .= $extra_params;',
        );
        $comment_config = ['extraParams' => ['cat', 'page']];
        $extra_params   = '';
        $cat            = 'a"b c<d>';
        $_GET['page']   = "x'y";
        try {
            eval("use Xmf\\Request;\n" . $code);
        } finally {
            unset($_GET['page']);
        }
        self::assertSame('cat=a%22b%20c%3Cd%3E&amp;page=x%27y&amp;', $extra_params);
    }

    private static function comment(int $id, int $modid, int $itemid, int $rootid, int $status = 2): object
    {
        return new class(compact('id', 'modid', 'itemid', 'rootid', 'status')) {
            public function __construct(private array $v)
            {
            }

            public function getVar(string $key): mixed
            {
                return match ($key) {
                    'com_id'     => $this->v['id'],
                    'com_modid'  => $this->v['modid'],
                    'com_itemid' => $this->v['itemid'],
                    'com_rootid' => $this->v['rootid'],
                    'com_status' => $this->v['status'],
                    default      => null,
                };
            }
        };
    }
}
