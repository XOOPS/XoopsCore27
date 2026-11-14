<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

use Xoops\ModuleTools\Internal\PngMetadata;

/**
 * Pure GD image resizer — a {@see ResizeRequest} in, a {@see ResizeResult} out.
 *
 * Consolidates the ~19 near-identical `Common\Resizer` copies scattered across modules into
 * one tested, side-effect-free class. Unlike those copies it:
 *   - is request-agnostic (no `$_POST`/`$_FILES`, no echo, no redirect),
 *   - exposes explicit fit modes (inside / cover / stretch) instead of one-method-per-shape,
 *   - corrects JPEG EXIF orientation (phone photos no longer come out sideways),
 *   - preserves the source format and PNG/GIF transparency, and
 *   - honours the requested quality consistently (the legacy copies hard-coded JPEG 100 in
 *     one path and left a width unset in another).
 *
 * GD-only by design (every legacy copy was GD); an Imagick strategy can be added later.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
final class Resizer implements ImageResizerInterface
{
    // ponytail: fixed ~50 MP budget (fits 48 MP phone photos); make it a ResizeRequest field if a module needs more
    private const int MAX_PIXELS = 50_000_000;
    private const string PNG_MIME = 'image/png';

    public function resize(ResizeRequest $request): ResizeResult
    {
        $target = $request->targetPath;

        if (!\extension_loaded('gd')) {
            return ResizeResult::failure($target, 'The GD extension is not available.');
        }
        if (!\is_file($request->sourcePath)) {
            return ResizeResult::failure($target, 'Source image not found: ' . $request->sourcePath);
        }
        // getimagesize() parses every format it knows (compressed SWF is inflated in memory),
        // so only the four decodable formats may reach it.
        $head = @\file_get_contents($request->sourcePath, false, null, 0, 12);
        if (false === $head) {
            return ResizeResult::failure($target, 'Not a readable image: ' . $request->sourcePath);
        }
        if (!self::hasSupportedSignature($head)) {
            return ResizeResult::failure($target, 'Unsupported image format: ' . $request->sourcePath);
        }
        $info = @\getimagesize($request->sourcePath);
        if (false === $info) {
            return ResizeResult::failure($target, 'Not a readable image: ' . $request->sourcePath);
        }
        $mime = (string) $info['mime'];
        // Decoding materialises the whole raster (4 bytes/px, plus a rotated copy for EXIF
        // JPEGs), so a small compressed file must not reach the decoder with huge dimensions.
        if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS || \max(1, $request->maxWidth) * \max(1, $request->maxHeight) > self::MAX_PIXELS) {
            return ResizeResult::failure($target, 'Image dimensions exceed the pixel budget: ' . $request->sourcePath);
        }
        if (self::PNG_MIME === $mime && !PngMetadata::isWithinBudget($request->sourcePath)) {
            return ResizeResult::failure($target, 'PNG metadata exceeds the decoding budget: ' . $request->sourcePath);
        }

        $image = $this->createImage($request->sourcePath, $mime);
        if (!$image instanceof \GdImage) {
            return ResizeResult::failure($target, 'Unsupported image format: ' . $mime);
        }
        if ('image/jpeg' === $mime) {
            $image = $this->applyExifOrientation($image, $request->sourcePath);
        }

        $srcW = \imagesx($image);
        $srcH = \imagesy($image);

        $outputMime = $request->outputMime ?? $mime;
        $canvas = $this->render($request, $image, $srcW, $srcH, $outputMime);
        if (!$this->save($canvas, $target, $outputMime, $request->quality)) {
            return ResizeResult::failure($target, 'Failed to write the target image: ' . $target);
        }

        return ResizeResult::success($target, \imagesx($canvas), \imagesy($canvas));
    }

    /** PNG, JPEG, GIF or WebP by the file's first 12 bytes. */
    private static function hasSupportedSignature(string $head): bool
    {
        return \str_starts_with($head, "\x89PNG\r\n\x1A\n")
            || \str_starts_with($head, "\xFF\xD8\xFF")
            || \str_starts_with($head, 'GIF87a')
            || \str_starts_with($head, 'GIF89a')
            || (\str_starts_with($head, 'RIFF') && 'WEBP' === \substr($head, 8, 4));
    }

    private function createImage(string $path, string $mime): ?\GdImage
    {
        $image = match ($mime) {
            self::PNG_MIME  => @\imagecreatefrompng($path),
            'image/jpeg' => @\imagecreatefromjpeg($path) ?: @\imagecreatefromstring((string) @\file_get_contents($path)),
            'image/gif'  => @\imagecreatefromgif($path),
            'image/webp' => \function_exists('imagecreatefromwebp') ? @\imagecreatefromwebp($path) : false,
            default      => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    /**
     * Rotate a JPEG to its EXIF "Orientation" so portrait phone photos are upright.
     * Handles the three common rotations (3/6/8); leaves the rarer flip cases as-is.
     */
    private function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!\function_exists('exif_read_data')) {
            return $image;
        }
        $exif        = @\exif_read_data($path);
        $orientation = \is_array($exif) ? (int) ($exif['Orientation'] ?? 0) : 0;

        $rotated = match ($orientation) {
            3       => \imagerotate($image, 180, 0),
            6       => \imagerotate($image, -90, 0),
            8       => \imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof \GdImage ? $rotated : $image;
    }

    private function render(ResizeRequest $r, \GdImage $src, int $srcW, int $srcH, string $mime): \GdImage
    {
        $maxW = \max(1, $r->maxWidth);
        $maxH = \max(1, $r->maxHeight);

        if (ResizeRequest::FIT_COVER === $r->fit) {
            // Scale to cover the box, then centre-crop to exactly maxW x maxH. Only the whole source
            // pixels under the (sub-pixel) crop are scaled: scaling the entire source would make a
            // thin image's intermediate (box / thin edge) times larger than either image. This one
            // stays below (maxW + 2*ratio + 1) x (maxH + 2*ratio + 1), and ratio <= max(maxW, maxH).
            $ratio   = \max($maxW / $srcW, $maxH / $srcH);
            $cropX   = \max(0.0, ($srcW - $maxW / $ratio) / 2); // max(): float error on the fitted edge
            $cropY   = \max(0.0, ($srcH - $maxH / $ratio) / 2);
            $x0      = (int) \floor($cropX);
            $y0      = (int) \floor($cropY);
            $w       = (int) \min($srcW, \ceil($srcW - $cropX)) - $x0;
            $h       = (int) \min($srcH, \ceil($srcH - $cropY)) - $y0;
            $scaledW = \max(1, (int) \ceil($w * $ratio));
            $scaledH = \max(1, (int) \ceil($h * $ratio));

            $canvas = $this->newCanvas($maxW, $maxH, $mime);
            if ($scaledW * $scaledH > 4 * $maxW * $maxH) {
                // ponytail: an extreme box (1x1 source into 10000x1) would need a huge intermediate for
                // sub-pixel centring; resample the covering pixels directly and accept <1 source px of drift.
                \imagecopyresampled($canvas, $src, 0, 0, $x0, $y0, $maxW, $maxH, $w, $h);

                return $canvas;
            }
            $scaled = $this->newCanvas($scaledW, $scaledH, $mime);
            \imagecopyresampled($scaled, $src, 0, 0, $x0, $y0, $scaledW, $scaledH, $w, $h);
            \imagecopy($canvas, $scaled, 0, 0, (int) \floor(($cropX - $x0) * $ratio), (int) \floor(($cropY - $y0) * $ratio), $maxW, $maxH);

            return $canvas;
        }

        if (ResizeRequest::FIT_STRETCH === $r->fit) {
            $newW = $maxW;
            $newH = $maxH;
        } else { // FIT_INSIDE
            $ratio = \min($maxW / $srcW, $maxH / $srcH);
            if (!$r->allowUpscale) {
                $ratio = \min($ratio, 1.0);
            }
            $newW = \max(1, (int) \round($srcW * $ratio));
            $newH = \max(1, (int) \round($srcH * $ratio));
        }

        $canvas = $this->newCanvas($newW, $newH, $mime);
        \imagecopyresampled($canvas, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

        return $canvas;
    }

    private function newCanvas(int $width, int $height, string $mime): \GdImage
    {
        $canvas = \imagecreatetruecolor(\max(1, $width), \max(1, $height));
        if (\in_array($mime, [self::PNG_MIME, 'image/gif', 'image/webp'], true)) {
            \imagealphablending($canvas, false);
            \imagesavealpha($canvas, true);
            $transparent = \imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            if (false !== $transparent) {
                \imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
            }
        }

        return $canvas;
    }

    private function save(\GdImage $image, string $path, string $mime, int $quality): bool
    {
        $quality = \max(0, \min(100, $quality));

        return match ($mime) {
            self::PNG_MIME  => \imagepng($image, $path, $this->pngLevel($quality)),
            'image/jpeg' => \imagejpeg($image, $path, $quality),
            'image/gif'  => \imagegif($image, $path),
            'image/webp' => \function_exists('imagewebp') && \imagewebp($image, $path, $quality),
            default      => false,
        };
    }

    /** Map quality 0–100 to PNG zlib compression 9–0 (higher quality => less compression). */
    private function pngLevel(int $quality): int
    {
        return \max(0, \min(9, 9 - (int) \round($quality / 100 * 9)));
    }
}
