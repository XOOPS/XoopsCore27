<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal;

/**
 * Whether one directory lies inside another, for recursive copies that must never walk
 * their own output.
 *
 * @internal
 */
final class PathContainment
{
    /**
     * A symlink, or a Windows junction: PHP reports a junction as is_dir() but not is_link(),
     * and its filetype() as "unknown".
     */
    public static function isLink(string $path): bool
    {
        return \is_link($path) || 'unknown' === @\filetype($path);
    }

    /** Whether `$path` is `$root` or below it; Windows paths compare case-insensitively. */
    public static function isInside(string $path, string $root): bool
    {
        $path = \rtrim(\str_replace('\\', '/', $path), '/') . '/';
        $root = \rtrim(\str_replace('\\', '/', $root), '/') . '/';

        return '\\' === \DIRECTORY_SEPARATOR
            ? 0 === \strncasecmp($path, $root, \strlen($root))
            : \str_starts_with($path, $root);
    }

    /**
     * Whether the existing directory `$path` is `$root` or below it, by device and inode, so a
     * case-insensitive filesystem (macOS APFS) cannot hide containment behind another spelling.
     * `$path` must be resolved (realpath): lexical ancestors of a link can skip the physical root.
     */
    public static function isInsideByIdentity(string $path, string $root): bool
    {
        $rootStat = @\stat($root);
        if (false === $rootStat || 0 === $rootStat['ino']) {
            return false;
        }
        $dir = $path;
        do {
            $stat = @\stat($dir);
            if (false !== $stat && $stat['ino'] === $rootStat['ino'] && $stat['dev'] === $rootStat['dev']) {
                return true;
            }
            $parent = $dir;
            $dir = \dirname($dir);
        } while ($dir !== $parent);

        return false;
    }
}
