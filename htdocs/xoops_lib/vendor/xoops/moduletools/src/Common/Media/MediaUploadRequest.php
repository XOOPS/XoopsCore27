<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common\Media;

/**
 * Immutable description of one image upload to ingest.
 *
 * Storage-agnostic: the caller supplies the absolute target directories and the
 * limits. Persistence is the consuming module's job (see {@see MediaRepositoryInterface}).
 *
 * @api Stable Common-tier API.
 * @since 1.3.0
 */
final readonly class MediaUploadRequest
{
    /**
     * @param array<string,mixed> $file            one $_FILES[...] entry
     * @param string              $targetDir       absolute dir for the original
     * @param string              $thumbnailDir    absolute dir for thumbnails ('' = none)
     * @param int[]               $thumbnailWidths widths to generate
     * @param string[]            $allowedMime     accepted MIME types
     */
    public function __construct(
        public array $file,
        public string $targetDir,
        public string $thumbnailDir = '',
        public array $thumbnailWidths = [150, 400],
        public array $allowedMime = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp'],
        public int $maxBytes = 5242880,
        public int $maxWidth = 4000,
        public int $maxHeight = 4000,
        public string $namePrefix = 'img',
        public int $quality = 85,
    ) {
    }
}
