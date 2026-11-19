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

namespace Xoops\Upgrade;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

use SplFileInfo;

/**
 * XOOPS Upgrade ScannerProcess
 *
 * Scanning process abstraction for use in ScannerWalker based file processing
 *
 * @category  Xoops\Upgrade
 * @package   Xoops
 * @author    Richard Griffith <richard@geekwright.com>
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
abstract class ScannerProcess
{
    /** Suffix of the staging file a repair writes beside the file it replaces */
    public const STAGING_SUFFIX = '.xotmp';

    abstract public function inspectFile(SplFileInfo $fileInfo);

    /**
     * Read a whole file without keeping a handle open (a repair renames over the
     * file afterwards). The read runs under a scoped handler, not @: the native
     * warning of a failed read would carry the full path, so a failure is
     * reported by the short name instead.
     *
     * @param string $pathname absolute path of the file to read
     * @param string $filename the name used in messages (relative to the site root)
     *
     * @return string|false the contents, or false after reporting a failed read
     */
    protected static function readContents(string $pathname, string $filename): string|false
    {
        set_error_handler(static fn (): bool => true);
        try {
            $contents = file_get_contents($pathname);
        } finally {
            restore_error_handler();
        }
        if (false === $contents) {
            trigger_error(sprintf('Error reading file: %s', $filename), E_USER_WARNING);
        }

        return $contents;
    }

    /**
     * Replace a file's contents without ever leaving it damaged: the new contents
     * are staged in a file beside it, and only a complete write is renamed over
     * the original. A failed or short write leaves the original as it was.
     *
     * A symlinked file is replaced at its target, so the link itself stays.
     * The caller must not hold an open handle on the file (Windows cannot rename
     * over an open file).
     *
     * @param string $pathname absolute path of the file to replace
     * @param string $filename the name used in messages (relative to the site root)
     * @param string $contents the new contents
     *
     * @return bool true when the file now holds $contents
     * @throws \Random\RandomException when the system has no source of randomness
     */
    protected static function replaceContents(string $pathname, string $filename, string $contents): bool
    {
        $tmpPath = '';
        $staged  = $replaced = $leftover = false;
        $chmodOk = true;
        // A scoped handler, not @: the native warnings of these calls would carry
        // the full path. Each failure is reported below by a short name instead.
        set_error_handler(static fn (): bool => true);
        try {
            $target = realpath($pathname);
            // A name of its own, created exclusively ('x'): an entry already at
            // that path, a symlink included, is never opened, and two repairs of
            // one file never share a staging file.
            $tmpPath = $target . '.' . bin2hex(random_bytes(6)) . self::STAGING_SUFFIX;
            $handle  = false === $target ? false : fopen($tmpPath, 'xb');
            if (false !== $handle) {
                $written = fwrite($handle, $contents);
                $staged  = fclose($handle) && strlen($contents) === $written;
                // The staging file has the process umask: give it the original's mode,
                // so the rename does not silently change the template's permissions.
                $perms    = $staged ? fileperms($target) : false;
                $chmodOk  = false === $perms || chmod($tmpPath, $perms & 0777);
                $replaced = $staged && rename($tmpPath, $target);
                $leftover = !$replaced && !unlink($tmpPath);
            }
        } finally {
            restore_error_handler();
        }

        if (!$chmodOk) {
            trigger_error(sprintf('Could not restore permissions on: %s', basename($filename)), E_USER_NOTICE);
        }
        if ($leftover) {
            trigger_error(
                sprintf('Could not remove staging file (%s): %s', $staged ? 'rename failed' : 'short write', basename($tmpPath)),
                E_USER_NOTICE
            );
        }
        if (!$replaced) {
            trigger_error(sprintf($staged ? 'Error replacing file: %s' : 'Error writing file: %s', $filename), E_USER_WARNING);
        }

        return $replaced;
    }

    /**
     * Convert an absolute pathname to a leading-slash path relative to
     * XOOPS_ROOT_PATH. Both sides are normalised to forward slashes first, so the
     * root prefix is trimmed regardless of the separator each side uses — without
     * this, a Windows "\" pathname against a "/" root leaves the full absolute
     * path in reports and scan tokens. The resolved root is tried as well: the
     * preflight scan walks realpath(XOOPS_ROOT_PATH), so on a symlinked document
     * root its pathnames would otherwise never match the configured root.
     *
     * @param string $pathname
     *
     * @return string
     */
    protected static function relativeToRoot(string $pathname): string
    {
        $path = str_replace('\\', '/', $pathname);
        foreach ([XOOPS_ROOT_PATH, (string) realpath(XOOPS_ROOT_PATH)] as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/');
            if ('' !== $root && str_starts_with($path, $root . '/')) {
                return substr($path, strlen($root));
            }
        }

        return $path;
    }
}
