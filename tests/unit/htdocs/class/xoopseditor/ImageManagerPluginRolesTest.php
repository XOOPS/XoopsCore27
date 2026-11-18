<?php
/**
 * Executed role and path cases for the editor image-manager plugin
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  XoopsTest
 * @package   XoopsCore27
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

declare(strict_types=1);

namespace Tests\Unit\ClassDir\XoopsEditor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\SourceFileTestTrait;

require_once dirname(__DIR__, 2) . '/modules/system/SourceFileTestTrait.php';

/**
 * Runs the request handling of xoopsimagemanager.php (one copy per TinyMCE
 * version) as a guest, a category reader, a category writer and an image
 * administrator. Reading a category must not open any write, writing one
 * category must not open another, and category changes stay with the
 * administrator. A delete removes a file only inside the upload folder,
 * whatever name the image row holds.
 *
 * The plugin cannot be included (it boots mainfile.php and prints a page), so
 * its request block is evaluated in a namespace where xoops_getHandler()
 * returns recording stand-ins. Category 5 is the one the reader may read and
 * the writer may write; category 6 is closed to both.
 */
final class ImageManagerPluginRolesTest extends TestCase
{
    use SourceFileTestTrait;

    private const SANDBOX = 'ImageManagerPluginRoles';

    private const ACTORS = [
        'guest'  => ['groups' => null, 'read' => [], 'write' => [], 'admin' => false],
        'reader' => ['groups' => [2], 'read' => [5], 'write' => [], 'admin' => false],
        'writer' => ['groups' => [2], 'read' => [5], 'write' => [5], 'admin' => false],
        'admin'  => ['groups' => [1], 'read' => [], 'write' => [], 'admin' => true],
    ];

    private const COPIES = [
        'tinymce7' => 'htdocs/class/xoopseditor/tinymce7/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php',
        'tinymce5' => 'htdocs/class/xoopseditor/tinymce5/js/tinymce/plugins/xoopsimagemanager/xoopsimagemanager.php',
    ];

    private string $workDir = '';

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var array<int, string> stored file name per image id */
    private array $imageNames = [51 => 'img51.png', 61 => 'img61.png'];

    protected function setUp(): void
    {
        $this->saved = [
            'post'     => $_POST,
            'get'      => $_GET,
            'files'    => $_FILES,
            'user'     => $GLOBALS['xoopsUser'] ?? null,
            'security' => $GLOBALS['xoopsSecurity'] ?? null,
        ];
        $this->workDir = sys_get_temp_dir() . '/xoimgroles_' . bin2hex(random_bytes(6));
        mkdir($this->workDir . '/uploads', 0777, true);
    }

    protected function tearDown(): void
    {
        $_POST                    = $this->saved['post'];
        $_GET                     = $this->saved['get'];
        $_FILES                   = $this->saved['files'];
        $GLOBALS['xoopsUser']     = $this->saved['user'];
        $GLOBALS['xoopsSecurity'] = $this->saved['security'];
        unset($GLOBALS['imageManagerRoleHandlers']);
        foreach ((array) glob($this->workDir . '/uploads/*') as $file) {
            unlink((string) $file);
        }
        foreach ((array) glob($this->workDir . '/*.png') as $file) {
            unlink((string) $file);
        }
        rmdir($this->workDir . '/uploads');
        rmdir($this->workDir);
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function refusedWrites(): array
    {
        $requests = [
            'reader uploads to the category it reads'        => ['reader', ['op' => 'addfile', 'imgcat_id' => 5]],
            'writer uploads to another category'             => ['writer', ['op' => 'addfile', 'imgcat_id' => 6]],
            'reader deletes an image of its category'        => ['reader', ['op' => 'delfileok', 'image_id' => 51]],
            'writer deletes an image of another category'    => ['writer', ['op' => 'delfileok', 'image_id' => 61]],
            'writer names its own category for that delete'  => ['writer', ['op' => 'delfileok', 'image_id' => 61, 'imgcat_id' => 5]],
            'reader adds a category'                         => ['reader', ['op' => 'addcat', 'imgcat_name' => 'New']],
            'writer adds a category'                         => ['writer', ['op' => 'addcat', 'imgcat_name' => 'New']],
            'reader updates a category'                      => ['reader', ['op' => 'updatecat', 'imgcat_id' => 5]],
            'writer updates the category it writes'          => ['writer', ['op' => 'updatecat', 'imgcat_id' => 5]],
            'reader deletes a category'                      => ['reader', ['op' => 'delcatok', 'imgcat_id' => 5]],
            'writer deletes the category it writes'          => ['writer', ['op' => 'delcatok', 'imgcat_id' => 5]],
        ];

        return self::forEachCopy($requests);
    }

    #[Test]
    #[DataProvider('refusedWrites')]
    public function aWriteWithoutTheRightIsRefusedBeforeAnythingChanges(string $path, string $actor, array $post): void
    {
        $result = $this->request($path, $actor, $post);

        self::assertSame(_NOPERM, $result['redirect']);
        self::assertSame([], $result['writes']);
        self::assertFileExists($this->workDir . '/uploads/img51.png');
        self::assertFileExists($this->workDir . '/uploads/img61.png');
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function guestWrites(): array
    {
        $requests = [];
        foreach (['addfile', 'delfileok', 'addcat', 'updatecat', 'delcatok'] as $op) {
            $requests['guest sends ' . $op] = ['guest', ['op' => $op, 'imgcat_id' => 5, 'image_id' => 51]];
        }

        return self::forEachCopy($requests);
    }

    #[Test]
    #[DataProvider('guestWrites')]
    public function aGuestWithoutAnyCategoryReachesNoWriteBranch(string $path, string $actor, array $post): void
    {
        $result = $this->request($path, $actor, $post);

        self::assertNull($result['redirect'], 'no branch ran, so nothing redirected');
        self::assertSame([], $result['writes']);
        self::assertFileExists($this->workDir . '/uploads/img51.png');
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>, string, list<string>}>
     */
    public static function allowedWrites(): array
    {
        $requests = [
            'writer uploads to its category'        => ['writer', ['op' => 'addfile', 'imgcat_id' => 5], 'no file', []],
            'administrator uploads to any category' => ['admin', ['op' => 'addfile', 'imgcat_id' => 6], 'no file', []],
            'writer deletes an image of its category' => ['writer', ['op' => 'delfileok', 'image_id' => 51], 'updated', ['image.delete']],
            'administrator deletes any image'       => ['admin', ['op' => 'delfileok', 'image_id' => 61], 'updated', ['image.delete']],
            'administrator adds a category'         => ['admin', ['op' => 'addcat', 'imgcat_name' => 'New'], 'updated', ['imagecategory.insert', 'groupperm.insert', 'groupperm.insert']],
            'administrator updates a category'      => ['admin', ['op' => 'updatecat', 'imgcat_id' => 5], 'updated', ['imagecategory.insert', 'groupperm.deleteAll', 'groupperm.insert', 'groupperm.insert']],
            'administrator deletes a category'      => ['admin', ['op' => 'delcatok', 'imgcat_id' => 5], 'updated', ['image.delete', 'imagecategory.delete']],
        ];

        return self::forEachCopy($requests);
    }

    /**
     * @param list<string> $writes
     */
    #[Test]
    #[DataProvider('allowedWrites')]
    public function aWriteWithTheRightGoesThrough(string $path, string $actor, array $post, string $redirect, array $writes): void
    {
        $result = $this->request($path, $actor, $post);

        // "no file": the upload passed the permission gate and stopped at the empty upload
        self::assertSame($redirect, $result['redirect']);
        self::assertSame($writes, $result['writes']);
    }

    /**
     * @return array<string, array{string, string, int, bool|string}>
     */
    public static function listings(): array
    {
        $requests = [
            'reader lists its category'       => ['reader', 5, true],
            'administrator lists any category' => ['admin', 6, true],
            'reader lists another category'   => ['reader', 6, _NOPERM],
            'guest lists a category'          => ['guest', 5, _NOPERM],
        ];

        return self::forEachCopy($requests);
    }

    #[Test]
    #[DataProvider('listings')]
    public function aCategoryIsListedOnlyForItsReaders(string $path, string $actor, int $category, bool|string $expected): void
    {
        $result = $this->request($path, $actor, [], ['op' => 'listimg', 'imgcat_id' => $category]);

        self::assertSame($expected, true === $expected ? $result['listed'] : $result['redirect']);
        self::assertSame([], $result['writes']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function copies(): array
    {
        return array_map(static fn (string $path): array => [$path], self::COPIES);
    }

    #[Test]
    #[DataProvider('copies')]
    public function deletingAnImageRemovesItsFileFromTheUploadFolder(string $path): void
    {
        $result = $this->request($path, 'writer', ['op' => 'delfileok', 'image_id' => 51]);

        self::assertSame('updated', $result['redirect']);
        self::assertFileDoesNotExist($this->workDir . '/uploads/img51.png');
        self::assertFileExists($this->workDir . '/uploads/img61.png');
    }

    #[Test]
    #[DataProvider('copies')]
    public function deletingAnImageNeverRemovesAFileOutsideTheUploadFolder(string $path): void
    {
        $this->imageNames[51] = '../outside.png';

        $result = $this->request($path, 'writer', ['op' => 'delfileok', 'image_id' => 51]);

        self::assertSame(['image.delete'], $result['writes'], 'the image row itself is still deleted');
        self::assertFileExists($this->workDir . '/outside.png');
    }

    #[Test]
    #[DataProvider('copies')]
    public function deletingACategoryRemovesOnlyFilesInsideTheUploadFolder(string $path): void
    {
        $this->imageNames = [51 => 'img51.png', 52 => '../outside.png'];

        $result = $this->request($path, 'admin', ['op' => 'delcatok', 'imgcat_id' => 5]);

        self::assertSame(['image.delete', 'image.delete', 'imagecategory.delete'], $result['writes']);
        self::assertFileDoesNotExist($this->workDir . '/uploads/img51.png');
        self::assertFileExists($this->workDir . '/outside.png');
    }

    /**
     * @param array<string, array<int, mixed>> $requests
     *
     * @return array<string, array<int, mixed>>
     */
    private static function forEachCopy(array $requests): array
    {
        $rows = [];
        foreach (self::COPIES as $copy => $path) {
            foreach ($requests as $name => $row) {
                $rows[$copy . ': ' . $name] = [$path, ...$row];
            }
        }

        return $rows;
    }

    /**
     * Run the plugin's request block for one actor.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $get
     *
     * @return array{redirect: string|null, writes: list<string>, listed: bool}
     */
    private function request(string $path, string $actor, array $post, array $get = []): array
    {
        $who = self::ACTORS[$actor];
        file_put_contents($this->workDir . '/outside.png', 'outside');
        file_put_contents($this->workDir . '/uploads/img51.png', 'inside');
        file_put_contents($this->workDir . '/uploads/img61.png', 'inside');

        $_POST  = $post + ([] === $post ? [] : ['target' => 'editor']);
        $_GET   = $get + ['target' => 'editor'];
        $_FILES = [];
        $GLOBALS['xoopsUser'] = null === $who['groups'] ? null : new class($who['groups']) {
            /** @param int[] $groups */
            public function __construct(private array $groups)
            {
            }

            /** @return int[] */
            public function getGroups(): array
            {
                return $this->groups;
            }
        };

        $writes = new \ArrayObject();
        $GLOBALS['xoopsSecurity']            = self::standIn('security', $writes, ['getErrors' => []]);
        $GLOBALS['imageManagerRoleHandlers'] = $this->handlers($who, $writes);

        $code = $this->requestBlock($path);
        foreach (['XOOPS_SYSTEM_IMAGE' => 9, '_AM_SYSTEM_DBUPDATED' => 'updated', '_FAILFETCHIMG' => 'no file',
                  '_AM_SYSTEM_IMAGES_FAILDEL' => 'faildel %s', '_AM_SYSTEM_IMAGES_FAILUNLINK' => 'failunlink %s',
                  '_AM_SYSTEM_IMAGES_FAILDELCAT' => 'faildelcat %s', '_AM_SYSTEM_IMAGES_SCATDELNG' => 'scatdelng'] as $name => $value) {
            // Namespaced, so the texts apply to the evaluated block only and no global constant is defined.
            defined(self::SANDBOX . '\\' . $name) || define(self::SANDBOX . '\\' . $name, $value);
        }
        if (!function_exists(self::SANDBOX . '\xoops_getHandler')) {
            eval('namespace ' . self::SANDBOX . '; function xoops_getHandler($name) { return $GLOBALS["imageManagerRoleHandlers"][$name]; }');
        }

        $uploadRoot = $this->workDir . '/uploads';
        $listed     = false;
        $redirect   = null;
        try {
            // eval() of repository source only: the plugin's own request block, read from the
            // tracked file above. Nothing from a request or an external source reaches it.
            eval('namespace ' . self::SANDBOX . "; use Xmf\\Request; use Criteria; use CriteriaCompo; use XoopsMediaUploader;\n" . $code);
        } catch (\RedirectHeaderException $e) {
            $redirect = $e->getMessage();
        }

        return ['redirect' => $redirect, 'writes' => $writes->getArrayCopy(), 'listed' => $listed];
    }

    /**
     * The request fields, the write branches and the listing guard of the plugin,
     * with the upload folder pointed at the test's own.
     */
    private function requestBlock(string $path): string
    {
        $this->loadSourceFile($path);
        $source = $this->sourceContent;

        $fields = self::between($source, '$op              = Request::hasVar', '// get current filename');
        $writes = self::between($source, '// check user/group - start', "\$GLOBALS['xoopsLogger']->activated = false;");
        $start  = strrpos($source, "if (\$op === 'listimg') {");
        self::assertNotFalse($start);
        $listing = self::between(substr($source, $start), "if (\$op === 'listimg') {", "\$image_handler = xoops_getHandler('image');");

        $code = $fields . "\$current_file = 'xoopsimagemanager.php';\n" . $writes . $listing . "\n\$listed = true;\n}";
        self::assertStringContainsString('XOOPS_UPLOAD_PATH', $code);

        return str_replace('XOOPS_UPLOAD_PATH', '$uploadRoot', $code);
    }

    private static function between(string $source, string $from, string $to): string
    {
        $start = strpos($source, $from);
        self::assertNotFalse($start, "start marker not found: $from");
        $end = strpos($source, $to, $start);
        self::assertNotFalse($end, "end marker not found after start: $to");

        return substr($source, $start, $end - $start);
    }

    /**
     * @param array{groups: ?array, read: int[], write: int[], admin: bool} $who
     *
     * @return array<string, object>
     */
    private function handlers(array $who, \ArrayObject $writes): array
    {
        $category = static fn (int $id): object => self::standIn('category', $writes, [
            'getVar' => static fn (string $key): mixed => ['imgcat_id' => $id, 'imgcat_type' => 'C', 'imgcat_name' => 'Category ' . $id][$key] ?? 0,
        ]);
        $image = fn (int $id): object => self::standIn('imageRow', $writes, [
            'getVar' => fn (string $key): mixed => ['image_id' => $id, 'imgcat_id' => $id < 60 ? 5 : 6, 'image_name' => $this->imageNames[$id]][$key] ?? 0,
        ]);

        return [
            'groupperm' => self::standIn('groupperm', $writes, [
                'checkRight' => static fn (string $name, int $id): bool => match ($name) {
                    'system_admin' => $who['admin'] && 9 === $id,
                    'imgcat_read'  => in_array($id, $who['read'], true),
                    'imgcat_write' => in_array($id, $who['write'], true),
                    default        => false,
                },
                'create' => static fn (): object => self::standIn('permission', $writes, []),
            ]),
            'imagecategory' => self::standIn('imagecategory', $writes, [
                'getList' => static fn (array $groups, string $perm): array => array_fill_keys($who['imgcat_read' === $perm ? 'read' : 'write'], 'Category'),
                'get'     => static fn (int $id): object|false => in_array($id, [5, 6], true) ? $category($id) : false,
                'create'  => static fn (): object => $category(7),
            ]),
            'image' => self::standIn('image', $writes, [
                'get'        => fn (int $id): object|false => isset($this->imageNames[$id]) ? $image($id) : false,
                'getObjects' => fn (): array => array_combine(
                    array_keys(array_filter($this->imageNames, static fn (int $id): bool => $id < 60, ARRAY_FILTER_USE_KEY)),
                    array_map($image, array_keys(array_filter($this->imageNames, static fn (int $id): bool => $id < 60, ARRAY_FILTER_USE_KEY)))
                ),
            ]),
        ];
    }

    /**
     * An object that answers any method: with the given closure or value, else
     * true. insert(), delete() and deleteAll() calls are logged as "label.method".
     *
     * @param array<string, mixed> $methods
     */
    private static function standIn(string $label, \ArrayObject $writes, array $methods): object
    {
        return new class($label, $writes, $methods) {
            /** @param array<string, mixed> $methods */
            public function __construct(private string $label, private \ArrayObject $writes, private array $methods)
            {
            }

            /** @param array<int, mixed> $arguments */
            public function __call(string $name, array $arguments): mixed
            {
                if (in_array($name, ['insert', 'delete', 'deleteAll'], true)) {
                    $this->writes->append($this->label . '.' . $name);
                }
                $answer = $this->methods[$name] ?? true;

                return $answer instanceof \Closure ? $answer(...$arguments) : $answer;
            }
        };
    }
}
