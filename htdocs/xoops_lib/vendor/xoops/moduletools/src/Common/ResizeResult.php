<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/**
 * Immutable outcome of an image resize. The resizer NEVER throws to output or redirects —
 * it returns this; the caller decides what to do with `$ok` / `$error`.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
final readonly class ResizeResult
{
    public function __construct(
        public bool $ok,
        public string $targetPath,
        public int $width,
        public int $height,
        public ?string $error = null,
    ) {
    }

    public static function success(string $targetPath, int $width, int $height): self
    {
        return new self(true, $targetPath, $width, $height, null);
    }

    public static function failure(string $targetPath, string $error): self
    {
        return new self(false, $targetPath, 0, 0, $error);
    }
}
