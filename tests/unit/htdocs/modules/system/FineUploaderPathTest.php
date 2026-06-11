<?php

declare(strict_types=1);

namespace modulessystem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/modules/system/class/fineuploadhandler.php';

/**
 * Path-confinement guards for the FineUploader handler (findings H-8 / A2-H-2).
 *
 * SystemFineUploadHandler is abstract, so we exercise its protected confinement
 * helpers through a concrete subclass and reflection, and assert the image/avatar
 * subclasses inherit (do not override) the confined methods.
 */
final class FineUploaderPathTest extends TestCase
{
    private function handler(): \SystemFineUploadHandler
    {
        return new class (new \stdClass()) extends \SystemFineUploadHandler {
            public $allowedExtensions = ['jpg', 'png', 'gif'];
        };
    }

    /**
     * @param mixed ...$args
     * @return mixed
     */
    private function call(object $obj, string $method, ...$args)
    {
        $m = new \ReflectionMethod($obj, $method);
        $m->setAccessible(true);
        return $m->invoke($obj, ...$args);
    }

    /** @return array<int, array{string}> */
    public static function badUuids(): array
    {
        return [
            ['../../etc'], ['..\\..\\x'], ['%2e%2e'], ['a/b'], ['a\\b'],
            ['C:'], [''], [str_repeat('a', 65)], ['has space'], ['dot.dot'],
        ];
    }

    #[Test]
    #[DataProvider('badUuids')]
    public function safeUuidRejectsUnsafeValues(string $uuid): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call($this->handler(), 'safeUuid', $uuid);
    }

    #[Test]
    public function safeUuidAcceptsPlainIdentifier(): void
    {
        self::assertSame('abc-123_DEF', $this->call($this->handler(), 'safeUuid', 'abc-123_DEF'));
    }

    #[Test]
    public function safeLeafNameStripsDirectoryAndKeepsAllowedExtension(): void
    {
        self::assertSame('photo.jpg', $this->call($this->handler(), 'safeLeafName', '../../photo.jpg'));
    }

    #[Test]
    public function safeLeafNameRejectsDisallowedExtension(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call($this->handler(), 'safeLeafName', 'shell.php');
    }

    #[Test]
    public function safeLeafNameRejectsDotfile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call($this->handler(), 'safeLeafName', '.htaccess');
    }

    #[Test]
    public function assertWithinRejectsParentTraversal(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call($this->handler(), 'assertWithin', '/srv/uploads/../escape', '/srv/uploads');
    }

    #[Test]
    public function assertWithinAllowsChildPath(): void
    {
        self::assertSame(
            '/srv/uploads/u/file.bin',
            $this->call($this->handler(), 'assertWithin', '/srv/uploads/u/file.bin', '/srv/uploads')
        );
    }

    #[Test]
    public function combineChunksRejectsZeroTotalParts(): void
    {
        // A zero (or negative) part count must be refused before any file is
        // opened, so a combine request cannot create an empty allowed-extension
        // file. The guard runs before any filesystem access.
        $_POST['qquuid']       = 'abc123';
        $_POST['qqtotalparts'] = '0';

        $this->expectException(\RuntimeException::class);
        try {
            $this->handler()->combineChunks(sys_get_temp_dir(), 'photo.jpg');
        } finally {
            unset($_POST['qquuid'], $_POST['qqtotalparts']);
        }
    }

    #[Test]
    public function declaredTotalSizeIsPinnedToPost(): void
    {
        // The declared total size must be read from POST only, so a GET/cookie
        // value cannot understate it and bypass the size limit.
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/class/fineuploadhandler.php');
        self::assertNotFalse($src);
        self::assertSame(
            1,
            preg_match("/Request::getInt\(\s*'qqtotalfilesize'\s*,\s*0\s*,\s*'POST'\s*\)/", $src),
            'qqtotalfilesize must be read from POST.'
        );
    }

    #[Test]
    public function uploadIdentifiersAreNotPostPinned(): void
    {
        // FineUploader emits qq* identifiers in the query string; pinning them to
        // POST returns empty and breaks every upload. Guard against re-pinning.
        $src = file_get_contents(XOOPS_ROOT_PATH . '/modules/system/class/fineuploadhandler.php');
        self::assertNotFalse($src);
        self::assertSame(0, preg_match("/getString\\(\\s*'qquuid'\\s*,\\s*''\\s*,\\s*'POST'\\s*\\)/", $src),
            'qquuid must be read from REQUEST, not POST-only.');
        self::assertSame(0, preg_match("/getInt\\(\\s*'qqtotalparts'\\s*,\\s*1\\s*,\\s*'POST'\\s*\\)/", $src),
            'qqtotalparts must be read from REQUEST, not POST-only.');
    }

    #[Test]
    public function handleDeleteRefusesMissingUuid(): void
    {
        // A DELETE with an empty URI must not let the target resolve to the
        // upload root and wipe it; the guard returns an error, dir stays intact.
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fu_del_' . getmypid();
        @mkdir($root, 0775, true);
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI']    = '';
        try {
            $result = $this->handler()->handleDelete($root);
            self::assertIsArray($result);
            self::assertArrayHasKey('error', $result);
            self::assertDirectoryExists($root, 'upload root must not be deleted');
        } finally {
            @rmdir($root);
            unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
        }
    }

    #[Test]
    public function subclassesInheritConfinedMethods(): void
    {
        require_once XOOPS_ROOT_PATH . '/modules/system/class/fineimuploadhandler.php';
        require_once XOOPS_ROOT_PATH . '/modules/system/class/fineavataruploadhandler.php';

        foreach (['SystemFineImUploadHandler', 'SystemFineAvatarUploadHandler'] as $sub) {
            foreach (['combineChunks', 'handleUpload', 'handleDelete'] as $method) {
                $declaring = (new \ReflectionMethod($sub, $method))->getDeclaringClass()->getName();
                self::assertSame(
                    'SystemFineUploadHandler',
                    $declaring,
                    "{$sub}::{$method}() must inherit the base confinement, not override it"
                );
            }
        }
    }
}
