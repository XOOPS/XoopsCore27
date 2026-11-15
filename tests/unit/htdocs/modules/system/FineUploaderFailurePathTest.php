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

namespace modulessystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/modules/system/class/fineuploadhandler.php';

/**
 * I/O failure paths of SystemFineUploadHandler: a failed chunk store, a failed
 * or short chunk assembly, and a failed delete must each report failure. A
 * failed assembly must keep the chunks for a retry, leave nothing at the final
 * path, and never remove a file already there.
 *
 * @category  Xoops
 * @package   System
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(\SystemFineUploadHandler::class)]
final class FineUploaderFailurePathTest extends TestCase
{
    private string $root;
    private bool $registeredWrapper = false;
    /** @var array{0: array, 1: array, 2: array, 3: array, 4: array} */
    private array $superglobals;

    protected function setUp(): void
    {
        // The handler reads these through Xmf\Request; the tests seed them and
        // restore the previous request afterwards. The HTTP status the failure
        // paths set needs no restore: the test bootstrap has already produced
        // output, so PHP ignores http_response_code() in this process.
        $this->superglobals = [$_GET, $_POST, $_REQUEST, $_FILES, $_SERVER];
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fu_fail_' . getmypid() . '_' . bin2hex(random_bytes(4));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'chunks', 0775, true);
        FailingUploadStream::reset();
        if (!in_array(FailingUploadStream::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(FailingUploadStream::SCHEME, FailingUploadStream::class);
            $this->registeredWrapper = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->registeredWrapper) {
            stream_wrapper_unregister(FailingUploadStream::SCHEME);
        }
        $this->removeTree($this->root);
        [$_GET, $_POST, $_REQUEST, $_FILES, $_SERVER] = $this->superglobals;
    }

    private function handler(): \SystemFineUploadHandler
    {
        $handler = new class (new \stdClass()) extends \SystemFineUploadHandler {};
        $handler->allowedExtensions = ['jpg'];
        $handler->allowedMimeTypes = [];
        $handler->chunksFolder = $this->root . DIRECTORY_SEPARATOR . 'chunks';
        return $handler;
    }

    /**
     * @param list<string> $parts chunk contents, in order
     */
    private function seedChunks(string $uuid, array $parts): string
    {
        $folder = $this->root . DIRECTORY_SEPARATOR . 'chunks' . DIRECTORY_SEPARATOR . $uuid;
        mkdir($folder, 0775, true);
        foreach ($parts as $i => $bytes) {
            file_put_contents($folder . DIRECTORY_SEPARATOR . $i, $bytes);
        }
        \Xmf\Request::setVar('qquuid', $uuid, 'REQUEST');
        \Xmf\Request::setVar('qqtotalparts', (string) count($parts), 'REQUEST');
        return $folder;
    }

    private static function assertChunksKept(string $folder): void
    {
        self::assertFileExists($folder . DIRECTORY_SEPARATOR . '0');
        self::assertFileExists($folder . DIRECTORY_SEPARATOR . '1');
    }

    #[Test]
    public function combineChunksAssemblesInOrderAndRemovesChunks(): void
    {
        $folder = $this->seedChunks('ok1', ['abc', 'def']);
        $uploads = $this->root . DIRECTORY_SEPARATOR . 'uploads';
        mkdir($uploads);

        $result = $this->handler()->combineChunks($uploads, 'photo.jpg');

        self::assertSame(['success' => true, 'uuid' => 'ok1'], $result);
        $dir = $uploads . DIRECTORY_SEPARATOR . 'ok1';
        self::assertSame('abcdef', file_get_contents($dir . DIRECTORY_SEPARATOR . 'photo.jpg'));
        self::assertSame(['.', '..', 'photo.jpg'], scandir($dir), 'no part file left behind');
        self::assertDirectoryDoesNotExist($folder);
    }

    #[Test]
    public function combineChunksReplacesAnExistingTarget(): void
    {
        $this->seedChunks('rep1', ['new', 'er']);
        $dir = $this->root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'rep1';
        mkdir($dir, 0775, true);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'photo.jpg', 'old');

        $result = $this->handler()->combineChunks($this->root . DIRECTORY_SEPARATOR . 'uploads', 'photo.jpg');

        self::assertSame(['success' => true, 'uuid' => 'rep1'], $result);
        self::assertSame('newer', file_get_contents($dir . DIRECTORY_SEPARATOR . 'photo.jpg'));
    }

    #[Test]
    public function combineChunksAcceptsANameNearTheLengthLimit(): void
    {
        // 238 bytes: accepted by safeLeafName(); a part file named after it
        // would pass the common 255-byte limit for one path component.
        $name = str_repeat('a', 234) . '.jpg';
        $this->seedChunks('long1', ['abc', 'def']);
        $uploads = $this->root . DIRECTORY_SEPARATOR . 'uploads';
        mkdir($uploads);

        $result = $this->handler()->combineChunks($uploads, $name);

        self::assertSame(['success' => true, 'uuid' => 'long1'], $result);
        self::assertSame('abcdef', file_get_contents($uploads . DIRECTORY_SEPARATOR . 'long1' . DIRECTORY_SEPARATOR . $name));
    }

    #[Test]
    public function combineChunksKeepsChunksWhenTheFileCannotBePublished(): void
    {
        $folder = $this->seedChunks('pub1', ['abc', 'def']);
        $dir = $this->root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pub1';
        // A directory where the target file should go: publishing onto it fails.
        mkdir($dir . DIRECTORY_SEPARATOR . 'photo.jpg', 0775, true);

        $result = @$this->handler()->combineChunks($this->root . DIRECTORY_SEPARATOR . 'uploads', 'photo.jpg');

        self::assertFalse($result['success']);
        self::assertArrayHasKey('error', $result);
        self::assertSame(['.', '..', 'photo.jpg'], scandir($dir), 'the part file is removed');
        self::assertChunksKept($folder);
    }

    #[Test]
    public function combineChunksCleansUpAndKeepsChunksOnShortWriteThenRetries(): void
    {
        $folder = $this->seedChunks('short1', ['abc', 'def']);
        FailingUploadStream::$dirs = ['root'];
        FailingUploadStream::$failWrites = true;
        $uploads = FailingUploadStream::SCHEME . '://root';

        $result = @$this->handler()->combineChunks($uploads, 'photo.jpg');

        self::assertFalse($result['success']);
        self::assertSame('short1', $result['uuid']);
        self::assertArrayHasKey('error', $result);
        self::assertStringNotContainsString($this->root, (string) json_encode($result));
        self::assertNotContains('root/short1/photo.jpg', FailingUploadStream::$files, 'nothing published');
        self::assertSame([], FailingUploadStream::$files, 'the partial part file is removed');
        self::assertCount(1, FailingUploadStream::$unlinkAttempts);
        self::assertStringEndsWith('.part', FailingUploadStream::$unlinkAttempts[0]);
        self::assertChunksKept($folder);

        FailingUploadStream::$failWrites = false;
        $retry = $this->handler()->combineChunks($uploads, 'photo.jpg');

        self::assertSame(['success' => true, 'uuid' => 'short1'], $retry);
        self::assertSame(['root/short1/photo.jpg'], FailingUploadStream::$files);
        self::assertDirectoryDoesNotExist($folder);
    }

    #[Test]
    public function combineChunksNeverRemovesAnExistingTargetOnFailure(): void
    {
        $this->seedChunks('dup1', ['abc', 'def']);
        FailingUploadStream::$dirs = ['root', 'root/dup1'];
        FailingUploadStream::$files = ['root/dup1/photo.jpg'];
        FailingUploadStream::$failWrites = true;

        $result = @$this->handler()->combineChunks(FailingUploadStream::SCHEME . '://root', 'photo.jpg');

        self::assertFalse($result['success']);
        self::assertSame(['root/dup1/photo.jpg'], FailingUploadStream::$files);
        self::assertNotContains('root/dup1/photo.jpg', FailingUploadStream::$unlinkAttempts);
    }

    #[Test]
    public function handleUploadReturnsErrorWhenChunkStoreFails(): void
    {
        $tmp = $this->root . DIRECTORY_SEPARATOR . 'php_tmp';
        file_put_contents($tmp, 'chunk-bytes');
        // Not an HTTP upload, so move_uploaded_file() refuses it: the store fails.
        $_FILES['qqfile'] = ['name' => 'photo.jpg', 'tmp_name' => $tmp, 'size' => 11, 'error' => 0, 'type' => 'image/jpeg'];
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=x';
        \Xmf\Request::setVar('qquuid', 'store1', 'REQUEST');
        \Xmf\Request::setVar('qqtotalparts', '2', 'REQUEST');
        \Xmf\Request::setVar('qqpartindex', '0', 'REQUEST');
        $uploads = $this->root . DIRECTORY_SEPARATOR . 'uploads';
        mkdir($uploads);

        $result = $this->handler()->handleUpload($uploads);

        self::assertIsArray($result, 'a failed chunk store must not fall through to null');
        self::assertArrayHasKey('error', $result);
        self::assertArrayNotHasKey('success', $result);
    }

    #[Test]
    public function handleDeleteRemovesTheUploadDirectory(): void
    {
        $uploads = $this->root . DIRECTORY_SEPARATOR . 'uploads';
        mkdir($uploads . DIRECTORY_SEPARATOR . 'del1' . DIRECTORY_SEPARATOR . 'sub', 0775, true);
        file_put_contents($uploads . DIRECTORY_SEPARATOR . 'del1' . DIRECTORY_SEPARATOR . 'photo.jpg', 'x');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Xmf\Request::setVar('qquuid', 'del1', 'REQUEST');

        $result = $this->handler()->handleDelete($uploads);

        self::assertSame(['success' => true, 'uuid' => 'del1'], $result);
        self::assertDirectoryDoesNotExist($uploads . DIRECTORY_SEPARATOR . 'del1');
    }

    #[Test]
    public function handleDeleteReportsFailureWhenAFileCannotBeRemoved(): void
    {
        FailingUploadStream::$dirs = ['root', 'root/del2'];
        FailingUploadStream::$files = ['root/del2/photo.jpg'];
        FailingUploadStream::$undeletable = ['root/del2/photo.jpg'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Xmf\Request::setVar('qquuid', 'del2', 'REQUEST');

        $result = $this->handler()->handleDelete(FailingUploadStream::SCHEME . '://root');

        self::assertFalse($result['success']);
        self::assertArrayHasKey('error', $result);
        self::assertStringNotContainsString('://', (string) json_encode($result), 'no server path in the response');
        self::assertContains('root/del2', FailingUploadStream::$dirs);
    }

    #[Test]
    public function endpointAnswersEveryFailedDeleteOutsideThe2xxRange(): void
    {
        // FineUploader treats any 2xx delete response as success, whatever the
        // body says, so the endpoint must turn each handleDelete() error array
        // (unwritable root, missing id, wrong method, not found, not removed)
        // into an error status. The script runs only over HTTP, so this pins
        // the DELETE branch in its source.
        $source = file_get_contents(XOOPS_ROOT_PATH . '/ajaxfineupload.php');
        self::assertIsString($source);
        $start = strpos($source, "} elseif (\$method == 'DELETE') {");
        $end = strpos($source, '} else {', (int) $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $branch = substr($source, $start, $end - $start);

        $normalise = strpos($branch, "if (true !== (\$result['success'] ?? false) && http_response_code() < 400) {\n        http_response_code(500);");
        self::assertNotFalse($normalise, 'a delete result without success:true must set an error status');
        self::assertGreaterThan(strpos($branch, 'handleDelete('), $normalise);
        self::assertLessThan(strpos($branch, 'echo json_encode($result);'), $normalise);
    }

    #[Test]
    public function handleDeleteJudgesAVoidRemoveDirOverrideByTheResultOnDisk(): void
    {
        $uploads = $this->root . DIRECTORY_SEPARATOR . 'uploads';
        mkdir($uploads . DIRECTORY_SEPARATOR . 'del3', 0775, true);
        file_put_contents($uploads . DIRECTORY_SEPARATOR . 'del3' . DIRECTORY_SEPARATOR . 'photo.jpg', 'x');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Xmf\Request::setVar('qquuid', 'del3', 'REQUEST');
        // A module subclass written against the old void signature.
        $handler = new class (new \stdClass()) extends \SystemFineUploadHandler {
            protected function removeDir($dir)
            {
                parent::removeDir($dir);
            }
        };

        $result = $handler->handleDelete($uploads);

        self::assertSame(['success' => true, 'uuid' => 'del3'], $result);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }
}

/**
 * Minimal in-memory stream wrapper: a filesystem whose writes can come up short
 * and whose listed files cannot be unlinked. Paths are normalised to
 * "root/sub/name"; $dirs and $files hold what exists.
 */
final class FailingUploadStream
{
    public const SCHEME = 'fufail';

    /** @var list<string> */
    public static array $dirs = [];
    /** @var list<string> */
    public static array $files = [];
    /** @var list<string> */
    public static array $undeletable = [];
    public static bool $failWrites = false;
    /** @var list<string> */
    public static array $unlinkAttempts = [];

    /** @var resource|null */
    public $context;
    /** @var list<string> */
    private array $entries = [];

    public static function reset(): void
    {
        self::$dirs = [];
        self::$files = [];
        self::$undeletable = [];
        self::$failWrites = false;
        self::$unlinkAttempts = [];
    }

    private static function key(string $path): string
    {
        $path = substr($path, strlen(self::SCHEME) + 3);
        return trim(str_replace('\\', '/', $path), '/');
    }

    /** @param list<string> $list */
    private static function without(array $list, string $key): array
    {
        return array_values(array_diff($list, [$key]));
    }

    public function url_stat(string $path, int $flags): array|false
    {
        $key = self::key($path);
        if (in_array($key, self::$dirs, true)) {
            return ['mode' => 0040777, 'size' => 0, 'uid' => 0, 'gid' => 0];
        }
        if (in_array($key, self::$files, true)) {
            return ['mode' => 0100666, 'size' => 1, 'uid' => 0, 'gid' => 0];
        }
        return false;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        self::$dirs[] = self::key($path);
        return true;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $key = self::key($path);
        if (!in_array($key, self::$files, true)) {
            self::$files[] = $key;
        }
        return true;
    }

    public function stream_write(string $data): int
    {
        return self::$failWrites ? 0 : strlen($data);
    }

    public function stream_close(): void
    {
    }

    public function unlink(string $path): bool
    {
        $key = self::key($path);
        self::$unlinkAttempts[] = $key;
        if (in_array($key, self::$undeletable, true)) {
            return false;
        }
        self::$files = self::without(self::$files, $key);
        return true;
    }

    public function rename(string $from, string $to): bool
    {
        $fromKey = self::key($from);
        if (!in_array($fromKey, self::$files, true)) {
            return false;
        }
        self::$files = self::without(self::without(self::$files, $fromKey), self::key($to));
        self::$files[] = self::key($to);
        return true;
    }

    public function rmdir(string $path, int $options): bool
    {
        $key = self::key($path);
        foreach (array_merge(self::$dirs, self::$files) as $entry) {
            if (dirname($entry) === $key) {
                return false;
            }
        }
        self::$dirs = self::without(self::$dirs, $key);
        return true;
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $key = self::key($path);
        $this->entries = ['.', '..'];
        foreach (array_merge(self::$dirs, self::$files) as $entry) {
            if (dirname($entry) === $key) {
                $this->entries[] = basename($entry);
            }
        }
        return true;
    }

    public function dir_readdir(): string|false
    {
        $entry = current($this->entries);
        next($this->entries);
        return $entry;
    }

    public function dir_rewinddir(): bool
    {
        reset($this->entries);
        return true;
    }

    public function dir_closedir(): bool
    {
        return true;
    }
}
