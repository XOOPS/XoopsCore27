<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal;

/**
 * libpng inflates zTXt, compressed iTXt and iCCP chunks while GD reads the header, before any
 * raster is allocated, so a pixel budget does not bound them: deflate expands up to ~1000x.
 *
 * @internal
 */
final class PngMetadata
{
    // ponytail: fixed 64 KiB compressed (~64 MB inflated at worst); ICC profiles and text are a few KB
    private const int MAX_COMPRESSED_BYTES = 65_536;

    /** Whether the PNG's compressed metadata fits the budget; false for an unreadable, malformed or truncated file. */
    public static function isWithinBudget(string $path): bool
    {
        $handle = @\fopen($path, 'rb');
        if (false === $handle) {
            return false;
        }
        try {
            return "\x89PNG\r\n\x1A\n" === \fread($handle, 8) && self::walkChunks($handle);
        } finally {
            \fclose($handle);
        }
    }

    /**
     * Every chunk is walked, IDAT skipped by seek: libpng also reads text after the image data.
     * A file without IEND is refused (a seek past EOF ends the walk there).
     *
     * @param resource $handle
     */
    private static function walkChunks($handle): bool
    {
        $compressed = 0;
        while (8 === \strlen($header = (string) \fread($handle, 8))) {
            ['length' => $length, 'type' => $type] = (array) \unpack('Nlength/a4type', $header);
            if ('IEND' === $type) {
                return 0 === $length && "\xAE\x42\x60\x82" === \fread($handle, 4); // empty, crc32('IEND')
            }
            $bytes = $length > 0x7FFFFFFF ? false : self::compressedBytes($handle, (string) $type, (int) $length);
            if (false === $bytes || ($compressed += $bytes) > self::MAX_COMPRESSED_BYTES) {
                return false;
            }
        }

        return false;
    }

    /**
     * The chunk's bytes libpng would inflate (0 for any other chunk), leaving the handle after
     * its CRC; false for a malformed chunk.
     *
     * @param resource $handle
     */
    private static function compressedBytes($handle, string $type, int $length): int|false
    {
        $bytes = 'zTXt' === $type || 'iCCP' === $type ? $length : 0;
        $read  = 0;
        if ('iTXt' === $type) {
            if (0 === $length) {
                return false; // malformed, and fread() refuses a zero length
            }
            // keyword (1-79 bytes), NUL, then the compression flag
            $head  = (string) \fread($handle, \min($length, 81));
            $read  = \strlen($head);
            $nul   = \strpos($head, "\0");
            $bytes = false === $nul || "\0" !== ($head[$nul + 1] ?? "\0") ? $length : 0;
        }
        \fseek($handle, $length - $read + 4, \SEEK_CUR); // rest of the data, then the CRC

        return $bytes;
    }
}
