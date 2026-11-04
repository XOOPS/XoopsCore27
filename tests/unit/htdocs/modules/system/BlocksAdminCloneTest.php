<?php
/**
 * Regression tests for modules/system/admin/blocksadmin/main.php — issue #73.
 *
 * Cloning a module block submits bid=0, so the save handler creates a fresh
 * block. It must carry the source block's module binding and name (issue #73),
 * and it takes them from the stored source row named by clone_bid: posted
 * callback names or paths are never used, so a forged form cannot point a
 * block at an arbitrary function or file.
 *
 * The clone branch is sliced from the save handler and executed as written
 * against a stub block handler; the bootstrap's redirect_header() throws.
 *
 * @copyright    2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package      Tests\Unit\System\BlocksAdmin
 */

declare(strict_types=1);

namespace Tests\Unit\System\BlocksAdmin;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/SourceFileTestTrait.php';
require_once XOOPS_ROOT_PATH . '/modules/system/class/block.php';
defined('_AM_SYSTEM_BLOCKS_INVALID_CLONE') || define('_AM_SYSTEM_BLOCKS_INVALID_CLONE', 'Invalid clone');

use Tests\Unit\System\SourceFileTestTrait;

class BlocksAdminCloneTest extends TestCase
{
    use SourceFileTestTrait;

    public const SOURCE_BID = 77;

    /** Module-binding fields a clone takes from its source block. */
    private const CLONE_FIELDS = [
        'mid'       => 4,
        'func_num'  => 2,
        'func_file' => 'nested/news_top.php',
        'show_func' => 'b_news_top_show',
        'edit_func' => 'b_news_top_edit',
        'template'  => 'news_block_top.tpl',
        'dirname'   => 'news',
        'name'      => 'Top News',
    ];

    protected function setUp(): void
    {
        $this->loadSourceFile('htdocs/modules/system/admin/blocksadmin/main.php');
    }

    /**
     * The clone branch of the save handler, without the closing brace of the
     * surrounding else.
     */
    private function cloneBranch(): string
    {
        $from  = "if ('D' === Request::getString('block_type', '', 'POST')) {";
        $to    = '// A stored block keeps its type';
        $start = strpos($this->sourceContent, $from);
        self::assertNotFalse($start, 'clone branch must be selected by the posted block type D');
        $end = strpos($this->sourceContent, $to, $start);
        self::assertNotFalse($end, 'end marker missing');
        $code = rtrim(substr($this->sourceContent, $start, $end - $start));
        self::assertStringEndsWith('}', $code);

        return 'use Xmf\Request; ' . substr($code, 0, -1);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private static function block(string $type, array $vars = []): \SystemBlock
    {
        $block = new \SystemBlock();
        $block->setVar('block_type', $type);
        foreach ($vars as $key => $value) {
            $block->setVar($key, $value);
        }
        $block->unsetNew();

        return $block;
    }

    /**
     * Run the clone branch for a posted form.
     *
     * @param array<string, mixed> $post
     */
    private function save(array $post, \SystemBlock $source): \SystemBlock
    {
        $block_handler = new class ($source) {
            public function __construct(private \SystemBlock $source)
            {
            }

            public function get(int $id): ?\SystemBlock
            {
                return BlocksAdminCloneTest::SOURCE_BID === $id ? $this->source : null;
            }
        };
        $block = new \SystemBlock();

        $saved = $_POST;
        $_POST = $post;
        try {
            eval($this->cloneBranch());
        } finally {
            $_POST = $saved;
        }

        return $block;
    }

    public function testCloneCopiesModuleBindingFromTheStoredSource(): void
    {
        $forged = [
            'block_type' => 'D',
            'clone_bid'  => (string) self::SOURCE_BID,
            'mid'        => '1',
            'func_file'  => 'other.php',
            'show_func'  => 'phpinfo',
            'edit_func'  => 'system',
            'dirname'    => 'system',
            'template'   => 'x.tpl',
            'name'       => 'forged',
        ];
        $clone = $this->save($forged, self::block('M', self::CLONE_FIELDS));

        foreach (self::CLONE_FIELDS as $field => $expected) {
            self::assertSame($expected, $clone->getVar($field, 'n'), "clone must take '{$field}' from the source block");
        }
    }

    public function testCloneOfUnknownSourceIsRefused(): void
    {
        $this->expectException(\RedirectHeaderException::class);
        $this->save(['block_type' => 'D', 'clone_bid' => '5'], self::block('M', self::CLONE_FIELDS));
    }

    public function testCloneWithoutSourceIsRefused(): void
    {
        $this->expectException(\RedirectHeaderException::class);
        $this->save(['block_type' => 'D', 'show_func' => 'phpinfo'], self::block('M', self::CLONE_FIELDS));
    }

    public function testModuleCloneOfACustomSourceIsRefused(): void
    {
        $this->expectException(\RedirectHeaderException::class);
        $this->save(['block_type' => 'D', 'clone_bid' => (string) self::SOURCE_BID], self::block('C'));
    }

    public function testNewCustomBlockGetsNoModuleBinding(): void
    {
        $block = $this->save(
            ['block_type' => 'C', 'clone_bid' => (string) self::SOURCE_BID, 'show_func' => 'phpinfo', 'func_file' => 'x.php'],
            self::block('M', self::CLONE_FIELDS),
        );

        self::assertSame('', (string) $block->getVar('show_func', 'n'));
        self::assertSame('', (string) $block->getVar('func_file', 'n'));
        self::assertSame(0, (int) $block->getVar('mid'));
    }

    /**
     * Regression guard: the normal edit path (bid > 0) must still load
     * the block from the handler.
     */
    public function testEditPathStillLoadsViaHandlerGet(): void
    {
        self::assertMatchesRegularExpression(
            '/if \(\$block_id > 0\) \{\s*\$block\s*=\s*\$block_handler->get\(\$block_id\);/',
            $this->sourceContent,
            'Edit path (bid > 0) must still load the existing block via $block_handler->get()'
        );
    }
}
