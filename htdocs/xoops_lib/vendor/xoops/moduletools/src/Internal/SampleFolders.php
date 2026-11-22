<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal;

use Xoops\ModuleTools\Common\SysUtility;

/**
 * Copies the `[source, destination]` sample-data folder pairs of a module's `config/config.php`
 * (`copyTestFolders`), for the install hook and for "Import Sample Data" alike.
 *
 * Sample data is optional: most modules keep the skeleton's `testdata/uploads` entry without
 * shipping that folder. Nothing to copy is not a failed copy.
 *
 * @internal
 */
final class SampleFolders
{
    /**
     * Copy every pair whose source folder the module ships.
     *
     * @param array<int|string, mixed> $pairs
     *
     * @return list<array{string, string}> the pairs that were not copied
     */
    public static function copy(array $pairs): array
    {
        $failed = [];
        foreach ($pairs as $pair) {
            [$source, $destination] = $pair;
            if (self::isAbsent((string)$source)) {
                continue;
            }
            if (!SysUtility::rcopy($source, $destination)) {
                $failed[] = [(string)$source, (string)$destination];
            }
        }

        return $failed;
    }

    /**
     * Whether $source is a plain local path that a readable ancestor folder confirms is not there.
     *
     * A false is_dir() alone does not say so: it is also false for a folder that exists below a
     * parent PHP may not search. Such a source goes to the copier, whose refusal is reported.
     */
    private static function isAbsent(string $source): bool
    {
        // A NUL byte or a URI is rcopy()'s to refuse: is_dir() throws on the first as of
        // PHP 8.6 and would ask a stream wrapper about the second.
        if ('' === $source || \str_contains($source, "\0") || \str_contains($source, '://') || \is_dir($source)) {
            return false;
        }

        // Walk up to the nearest ancestor that is a folder and ask it for the missing entry.
        // The path stays as configured: dirname() and basename() split it the way the copier
        // will, and both ignore a trailing separator.
        $path   = $source;
        $parent = \dirname($path);
        while ($parent !== $path && !\is_dir($parent)) {
            $path   = $parent;
            $parent = \dirname($path);
        }
        $entries = $parent !== $path && \is_readable($parent) ? \scandir($parent) : false;

        return \is_array($entries) && !\in_array(\basename($path), $entries, true);
    }
}
