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
 * Values carried from one request to the next must come back unchanged.
 *
 * findusers.php re-posted every field as name='groups', so PHP kept only the
 * last of several selected groups and the next page lost the group filter; a
 * nested field reached htmlspecialchars() as an array. comment_delete.php and
 * comment_post.php joined a module's extraParams with "&amp;" and no URL
 * encoding, so a value holding "&" split into two parameters, and the delete
 * path dropped the parameters the confirmation form posts back.
 *
 * Each block is sliced from its file and executed, as in
 * CommentModuleOwnershipTest.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class RepostQueryEncodingTest extends TestCase
{
    use SourceFileTestTrait;

    private const FINDUSERS = 'htdocs/include/findusers.php';
    private const DELETE    = 'htdocs/include/comment_delete.php';
    private const POST      = 'htdocs/include/comment_post.php';

    private static int $sequence = 0;

    protected function tearDown(): void
    {
        $_GET  = [];
        $_POST = [];
        unset($GLOBALS['xoopsSecurity']);
    }

    #[Test]
    public function findusersRepostsEverySelectedValueUnderItsOwnKey(): void
    {
        $fn = $this->defineHiddenFieldsFunction();
        $GLOBALS['xoopsSecurity'] = new class {
            public function getTokenHTML(): string
            {
                return '<input type="hidden" name="XOOPS_TOKEN_REQUEST" value="fresh" />';
            }
        };

        $html = $fn([
            'groups'              => ['2', '3'],
            'user_uname'          => "O'Brien & <b>",
            'deep'                => ['a' => ['b' => 'x']],
            'XOOPS_TOKEN_REQUEST' => 'stale',
        ]);

        self::assertSame(
            ['groups' => ['2', '3'], 'user_uname' => "O'Brien & <b>", 'deep' => ['a' => ['b' => 'x']], 'XOOPS_TOKEN_REQUEST' => 'fresh'],
            self::parseHiddenInputs($html),
        );
        self::assertStringNotContainsString('<b>', $html);
        self::assertStringNotContainsString('stale', $html);
    }

    #[Test]
    public function findusersBuildsBothPagingFormsWithTheHelper(): void
    {
        $this->loadSourceFile(self::FINDUSERS);
        self::assertStringNotContainsString('foreach ($_POST as', $this->sourceContent);
        self::assertSame(2, substr_count($this->sourceContent, '$hiddenform .= xoops_findusers_hidden_fields($_POST);'));
    }

    #[Test]
    public function commentDeleteEncodesExtraParamsAndReadsThePostedConfirmation(): void
    {
        $_GET  = ['forum' => 'a&b=c'];
        $_POST = ['topic' => '7 8'];
        $vars  = $this->runDeleteBlock(['forum', 'topic', 'post', 'missing'], ['post' => 'x#y']);

        self::assertSame('viewtopic.php?forum=a%26b%3Dc&topic=7+8&post=x%23y&topic_id', $vars['redirect_page']);
        self::assertSame(['forum' => 'a&b=c', 'topic' => '7 8', 'post' => 'x#y'], $vars['comment_confirm_extra']);
    }

    #[Test]
    public function commentDeleteSkipsANonScalarLocalAndKeepsAPlainPageWithoutExtras(): void
    {
        $vars = $this->runDeleteBlock(['forum'], ['forum' => ['x']]);
        self::assertSame('viewtopic.php?topic_id', $vars['redirect_page']);

        $vars = $this->runDeleteBlock([], []);
        self::assertSame('viewtopic.php?topic_id', $vars['redirect_page']);
    }

    #[Test]
    public function commentPostEncodesExtraParamsAndKeepsEmptyOnes(): void
    {
        $_POST = ['forum' => 'a&b', 'topic' => '"q"'];
        $this->loadSourceFile(self::POST);
        $block = self::slice($this->sourceContent, '    $redirect_page  = $comment_config[\'pageName\'] . \'?\';', '$redirect_page .= $comment_config[\'itemName\'];');

        $comment_config = ['pageName' => 'viewtopic.php', 'itemName' => 'topic_id', 'extraParams' => ['forum', 'topic', 'post']];
        eval(self::scope() . $block);

        self::assertSame('forum=a%26b&topic=%22q%22&post=&', $extra_params);
        self::assertSame('viewtopic.php?forum=a%26b&topic=%22q%22&post=&topic_id', $redirect_page);
    }

    #[Test]
    public function commentPostStoresExtraParamsOnlyWhenTheyFitTheColumn(): void
    {
        $this->loadSourceFile(self::POST);
        $block = self::slice($this->sourceContent, 'if (isset($extra_params) && \strlen($extra_params) <= 255) {', '}');

        foreach ([255 => true, 256 => false] as $length => $stored) {
            $comment = new class {
                /** @var array<string, mixed> */
                public array $vars = [];

                public function setVar(string $key, mixed $value): void
                {
                    $this->vars[$key] = $value;
                }
            };
            $extra_params = 'f=' . str_repeat('x', $length - 2);
            eval(self::scope() . $block);
            self::assertSame($stored ? ['com_exparams' => $extra_params] : [], $comment->vars, "$length characters");
        }
    }

    #[Test]
    public function recentCommentsBlockLinksBothStoredFormats(): void
    {
        $this->loadSourceFile('htdocs/modules/system/blocks/system_blocks.php');
        $line = self::slice($this->sourceContent, '$com[\'title\'] = \'<a href="\' . XOOPS_URL', "</a>';");

        $stub = static fn (array $vars): object => new class($vars) {
            /** @param array<string, mixed> $vars */
            public function __construct(private array $vars)
            {
            }

            public function getVar(string $key): mixed
            {
                return $this->vars[$key] ?? '';
            }
        };
        $mid            = 5;
        $i              = 9;
        $modules        = [$mid => $stub(['dirname' => 'newbb'])];
        $comment_config = [$mid => ['pageName' => 'viewtopic.php', 'itemName' => 'topic_id']];
        foreach (['forum=1&amp;post=2&amp;', 'forum=1&post=2&'] as $stored) {
            $comments = [$i => $stub(['com_itemid' => 3, 'com_rootid' => 9, 'com_title' => 'T', 'com_exparams' => $stored])];
            $com      = [];
            eval(self::scope() . $line);
            self::assertMatchesRegularExpression('/href="([^"]*)"/', $com['title']);
            preg_match('/href="([^"]*)"/', $com['title'], $m);
            parse_str((string) parse_url(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), PHP_URL_QUERY), $query);
            self::assertSame(['topic_id' => '3', 'com_id' => '9', 'com_rootid' => '9', 'forum' => '1', 'post' => '2'], $query, $stored);
        }
    }

    #[Test]
    public function commentPostNotificationLinkUsesTheSameEncoding(): void
    {
        $this->loadSourceFile(self::POST);
        $start = strpos($this->sourceContent, 'if (!isset($comment_url)) {');
        self::assertNotFalse($start);
        $end = strpos($this->sourceContent, "\$comment_tags['X_COMMENT_URL']", $start);
        self::assertNotFalse($end);
        $loop = substr($this->sourceContent, $start, $end - $start);
        self::assertStringContainsString("http_build_query(\$extraArgs, '', '&')", $loop);
        self::assertStringNotContainsString('&amp;', $loop);
    }

    #[Test]
    public function commentRedirectsUsePlainAmpersands(): void
    {
        foreach ([self::DELETE, self::POST] as $file) {
            $this->loadSourceFile($file);
            self::assertDoesNotMatchRegularExpression('/redirect_header\([^;]*&amp;/', $this->sourceContent, "$file: redirect_header() takes a raw URL");
        }
    }

    #[Test]
    public function commentDeleteBackLinkIsEscapedAndModeIsAllowListed(): void
    {
        $this->loadSourceFile(self::DELETE);
        self::assertStringContainsString("echo '<br><a href=\"' . htmlspecialchars(\$redirect_page . '=' . \$com_itemid . '&com_order=' . \$com_order . '&com_mode=' . \$com_mode, ENT_QUOTES | ENT_HTML5) . '\">'", $this->sourceContent);
        self::assertStringContainsString("if (!in_array(\$com_mode, ['flat', 'thread', 'nest'], true)) {", $this->sourceContent);
    }

    /**
     * @param list<string>         $extraParams
     * @param array<string, mixed> $locals variables the including module page set
     *
     * @return array<string, mixed>
     */
    private function runDeleteBlock(array $extraParams, array $locals): array
    {
        $this->loadSourceFile(self::DELETE);
        $block = self::slice($this->sourceContent, '    $redirect_page         = $comment_config[\'pageName\'] . \'?\';', '$redirect_page .= $comment_config[\'itemName\'];');

        return (static function (string $code, array $comment_config, array $locals): array {
            extract($locals);
            eval($code);

            return get_defined_vars();
        })(self::scope() . $block, ['pageName' => 'viewtopic.php', 'itemName' => 'topic_id', 'extraParams' => $extraParams], $locals);
    }

    /**
     * @return callable(array<string, mixed>): string
     */
    private function defineHiddenFieldsFunction(): callable
    {
        $this->loadSourceFile(self::FINDUSERS);
        $block = self::slice($this->sourceContent, 'function xoops_findusers_hidden_fields(', "\n}\n");
        $ns    = __NAMESPACE__ . '\\FindusersHiddenFields' . ++self::$sequence;
        eval('namespace ' . $ns . ";\n" . $block);

        return $ns . '\\xoops_findusers_hidden_fields';
    }

    private static function scope(): string
    {
        return 'namespace ' . __NAMESPACE__ . '\\Repost' . ++self::$sequence . ";\nuse Xmf\\Request;\n";
    }

    private static function slice(string $source, string $from, string $through): string
    {
        $start = strpos($source, $from);
        self::assertNotFalse($start, "marker not found: $from");
        $end = strpos($source, $through, $start);
        self::assertNotFalse($end, "end marker not found: $through");

        return substr($source, $start, $end - $start + \strlen($through));
    }

    /**
     * Parse hidden inputs the way PHP parses the form they would post.
     *
     * @return array<string, mixed>
     */
    private static function parseHiddenInputs(string $html): array
    {
        preg_match_all('/<input type=([\'"])hidden\1 name=([\'"])(.*?)\2 value=([\'"])(.*?)\4/', $html, $m, PREG_SET_ORDER);
        $pairs = [];
        foreach ($m as $input) {
            $name    = html_entity_decode($input[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value   = html_entity_decode($input[5], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
        }
        parse_str(str_replace(['%5B', '%5D'], ['[', ']'], implode('&', $pairs)), $parsed);

        return $parsed;
    }
}
