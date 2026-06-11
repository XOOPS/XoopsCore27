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
