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

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__) . '/modules/system/SourceFileTestTrait.php';

/**
 * When comment_post.php cannot store a new top comment's root id, it deletes
 * the comment. It must then report a message and stop, instead of throwing
 * ArgumentCountError from xoops_error() or running on into approval,
 * notification and the "thanks" redirect for a comment that no longer exists.
 * The block is sliced from the file and executed inside a switch, as it runs
 * in the shipped script.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CommentPostRootFailureTest extends TestCase
{
    use SourceFileTestTrait;

    private string $directory;

    protected function setUp(): void
    {
        // Real xoops_error() needs the theme; record the call instead (separate process, no clash).
        eval('function xoops_error($msg, $title = "") { $GLOBALS["steps"][] = "error:" . $msg; }');
        $this->directory = sys_get_temp_dir() . '/xoops-compost-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        foreach (['header', 'footer'] as $page) {
            file_put_contents($this->directory . "/$page.php", "<?php \$GLOBALS['steps'][] = '$page';");
        }
        $directory        = $this->directory;
        $GLOBALS['xoops'] = new class($directory) {
            public function __construct(private string $directory)
            {
            }

            public function path(string $relPath): string
            {
                return $this->directory . '/' . $relPath;
            }
        };
        $GLOBALS['steps'] = [];
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*.php') ?: []);
        rmdir($this->directory);
    }

    /**
     * Runs the root-id block the way the 'post' case does and returns its locals.
     *
     * @return array{reached: bool, rootid: int, results: mixed, handler: object}
     */
    private function runBlock(bool $updateSucceeds, bool $purge): array
    {
        $this->loadSourceFile('htdocs/include/comment_post.php');
        $from  = 'if ($com_rootid == 0) {';
        $to    = '// call custom approve function';
        $start = strpos($this->sourceContent, $from);
        self::assertNotFalse($start, 'start marker missing');
        $end = strpos($this->sourceContent, $to, $start);
        self::assertNotFalse($end, 'end marker missing');
        $block = substr($this->sourceContent, $start, $end - $start);

        $comment_handler = new class($updateSucceeds) {
            public array $calls = [];

            public function __construct(private bool $updateSucceeds)
            {
            }

            public function updateByField(object $comment, string $field, int $value): bool
            {
                $this->calls[] = "update:$field=$value";

                return $this->updateSucceeds;
            }

            public function delete(object $comment): bool
            {
                $this->calls[] = 'delete';

                return true;
            }
        };
        $comment    = new \stdClass();
        $com_rootid = 0;
        $newcid     = 42;
        if ($purge) {
            $purge_comment_post_results = true;
        }
        $reached = false;
        eval('switch (1) { case 1: ' . $block . ' $reached = true; break; }');

        return [
            'reached' => $reached,
            'rootid'  => $com_rootid,
            'results' => $comment_post_results ?? null,
            'handler' => $comment_handler,
        ];
    }

    #[Test]
    public function aFailedRootUpdateDeletesReportsAndStops(): void
    {
        $result = $this->runBlock(false, false);

        self::assertFalse($result['reached'], 'the post continued after the comment was deleted');
        self::assertSame(['update:com_rootid=42', 'delete'], $result['handler']->calls);
        self::assertSame(['header', 'error:Could not save the comment.', 'footer'], $GLOBALS['steps']);
    }

    #[Test]
    public function aFailedRootUpdateInResultsModeReturnsTheMessageWithoutOutput(): void
    {
        $result = $this->runBlock(false, true);

        self::assertFalse($result['reached']);
        self::assertSame(['Could not save the comment.'], $result['results']);
        self::assertSame([], $GLOBALS['steps']);
    }

    #[Test]
    public function aSuccessfulRootUpdateContinues(): void
    {
        $result = $this->runBlock(true, false);

        self::assertTrue($result['reached']);
        self::assertSame(42, $result['rootid']);
        self::assertSame(['update:com_rootid=42'], $result['handler']->calls);
        self::assertSame([], $GLOBALS['steps']);
    }

    #[Test]
    public function theMessageConstantIsDefinedInTheEnglishLanguageFile(): void
    {
        $this->loadSourceFile('htdocs/language/english/comment.php');
        self::assertStringContainsString("define('_CM_COMPOSTNG', 'Could not save the comment.');", $this->sourceContent);
    }
}
