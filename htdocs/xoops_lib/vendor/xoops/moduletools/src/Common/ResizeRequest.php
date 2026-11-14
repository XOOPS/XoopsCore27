<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/**
 * Immutable input for an image resize. Pure data — no request reads, no I/O.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
final readonly class ResizeRequest
{
    /** Scale to fit INSIDE maxWidth x maxHeight, preserving aspect (no crop). */
    public const string FIT_INSIDE = 'inside';

    /** Scale to COVER maxWidth x maxHeight, then centre-crop to exactly those dims. */
    public const string FIT_COVER = 'cover';

    /** Stretch to exactly maxWidth x maxHeight, ignoring aspect ratio. */
    public const string FIT_STRETCH = 'stretch';

    /**
     * @param string $sourcePath  path of the image to read
     * @param string $targetPath  path to write the resized image to
     * @param int    $maxWidth    target width bound (px)
     * @param int    $maxHeight   target height bound (px)
     * @param string $fit         one of FIT_INSIDE | FIT_COVER | FIT_STRETCH
     * @param int    $quality     output quality 0–100 (JPEG/WebP; mapped to PNG compression)
     * @param bool   $allowUpscale whether to enlarge images smaller than the target
     * @param string|null $outputMime optional target encoding; null preserves the source format
     */
    public function __construct(
        public string $sourcePath,
        public string $targetPath,
        public int $maxWidth,
        public int $maxHeight,
        public string $fit = self::FIT_INSIDE,
        public int $quality = 85,
        public bool $allowUpscale = false,
        public ?string $outputMime = null,
    ) {
    }
}
