<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.
*/

/**
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author    XOOPS Development Team
 */

use Xoops\Helpers\Utility\Filesystem;
use Xoops\Helpers\Utility\HtmlBuilder;
use Xoops\ModuleTools\Internal\PackageLanguage;
use Xoops\ModuleTools\Internal\Webmaster;

/**
 * Class DirectoryChecker
 * check status of a directory
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @deprecated since 1.4.0. Use Xoops\Helpers\Utility\Filesystem for new code; retained through XOOPS 2.8.
 */
class DirectoryChecker
{
    // ponytail: fixed cap on new levels per call; no upload or cache tree nests this deep
    private const int MAX_NEW_LEVELS = 32;

    /**
     * @param     $path
     * @param int $mode
     * @param     $redirectFile
     *
     * @return bool|string
     */
    public static function getDirectoryStatus($path, $mode = 0755, $redirectFile = null)
    {
        $pathIcon16 = \Xmf\Module\Admin::iconUrl('', '16');

        if (empty($path)) {
            return false;
        }
        // Normalise once: the comparison, the message and the button must all use the mode
        // that createDirectory()/setDirectoryPermissions() will actually apply (0777 -> 0755),
        // or the page keeps offering "Set the permission" after the admin has clicked it.
        $mode         = self::normalizeMode($mode, 0755);
        $displayPath  = self::escape((string)$path);
        $redirectFile = self::safeRedirect($redirectFile);

        if (!@\is_dir($path)) {
            $path_status = "<img src='$pathIcon16/0.png' >";
            $path_status .= "$displayPath (" . self::message('NOTAVAILABLE', 'not available') . ') ';
            $path_status .= self::actionForm('mtools_createdir', (string)$path, $redirectFile, self::message('CREATETHEDIR', 'Create it'), $mode);
        } elseif (@\is_writable($path)) {
            $path_status = "<img src='$pathIcon16/1.png' >";
            $path_status .= "$displayPath (" . self::message('AVAILABLE', 'available') . ') ';
            $currentMode = mb_substr(\decoct(\fileperms($path)), 2);
            if ($currentMode != \decoct($mode)) {
                $path_status = "<img src='$pathIcon16/0.png' >";
                $path_status .= $displayPath . ' ' . sprintf(
                    self::message('NOTWRITABLE', 'should be writable as %s; current mode is %s'),
                    \decoct($mode),
                    $currentMode
                );
                $path_status .= ' ' . self::actionForm('mtools_setdirperm', (string)$path, $redirectFile, self::message('SETMPERM', 'Set the permission'), $mode);
            }
        } else {
            $currentMode = mb_substr(\decoct(\fileperms($path)), 2);
            $path_status = "<img src='$pathIcon16/0.png' >";
            $path_status .= $displayPath . ' ' . sprintf(
                self::message('NOTWRITABLE', 'should be writable as %s; current mode is %s'),
                \decoct($mode),
                $currentMode
            );
            $path_status .= ' ' . self::actionForm('mtools_setdirperm', (string)$path, $redirectFile, self::message('SETMPERM', 'Set the permission'), $mode);
        }

        return $path_status;
    }

    /**
     * Handle the "Create it" / "Set the permission" button posts.
     *
     * Call it from an admin page BEFORE any output; it redirects when it acts.
     */
    public static function handleRequest(?string $redirectFile = null, ?string $allowedBasePath = null): void
    {
        $op = (string)($_POST['op'] ?? '');
        if ('mtools_createdir' !== $op && 'mtools_setdirperm' !== $op) {
            return;
        }

        $path         = (string)($_POST['path'] ?? '');
        $mode         = (int)\octdec((string)($_POST['mode'] ?? '0755'));
        $redirectFile = self::safeRedirect($redirectFile);

        // Only a webmaster may create directories or change modes on the server. A module admin is
        // not enough even with a caller-chosen base: a shared base such as XOOPS_UPLOAD_PATH still
        // holds every other module's directories.
        if (!Webmaster::isCurrentUser()) {
            \redirect_header($redirectFile, 3, \defined('_NOPERM') ? \_NOPERM : 'Permission denied.');
            exit;
        }
        // Fail closed: no security service means no token check is possible, so no write.
        $security = self::runtimeGlobal('xoopsSecurity');
        if (!\is_object($security) || !\method_exists($security, 'check') || !$security->check()) {
            $errors = (\is_object($security) && \method_exists($security, 'getErrors'))
                ? \implode('<br>', $security->getErrors())
                : 'Security token missing or invalid';
            \redirect_header($redirectFile, 3, '' !== $errors ? $errors : 'Security token missing or invalid');
            exit;
        }

        if ('mtools_createdir' === $op) {
            $ok  = self::createDirectory($path, $mode, $allowedBasePath);
            $msg = $ok ? self::message('DIRCREATED', 'The directory has been created') : self::message('DIRNOTCREATED', 'The directory cannot be created');
        } else {
            $ok  = self::setDirectoryPermissions($path, $mode, $allowedBasePath);
            $msg = $ok ? self::message('PERMSET', 'The permission has been set') : self::message('PERMNOTSET', 'The permission cannot be set');
        }

        \redirect_header($redirectFile, 2, $msg . ': ' . self::escape($path));
        exit;
    }

    /**
     * @param     $target
     * @param int $mode
     */
    public static function createDirectory($target, $mode = 0755, ?string $allowedBasePath = null): bool
    {
        if (!self::isAllowedPath((string)$target, $allowedBasePath)) {
            return false;
        }

        // The path is validated once: with ".." refused and the nearest existing ancestor inside
        // the base, every missing component is a plain child of it. Collected once and created
        // top-down, so a deep path costs one walk, not one full re-validation per level.
        $missing = [];
        for ($dir = (string)$target; !\is_dir($dir); $dir = \dirname($dir)) {
            if (\count($missing) >= self::MAX_NEW_LEVELS || $dir === \dirname($dir)) {
                return false;
            }
            $missing[] = $dir;
        }
        return \array_all(
            \array_reverse($missing),
            // Re-checked per level (its parent now exists, so this is one realpath): a level
            // swapped for a link after the first check must not carry the next mkdir outside.
            static fn (string $dir): bool => self::isAllowedPath($dir, $allowedBasePath)
                && (Filesystem::mkdir($dir, self::normalizeMode($mode, 0755), false) || \is_dir($dir)),
        );
    }

    /**
     * @param     $target
     * @param int $mode
     */
    public static function setDirectoryPermissions($target, $mode = 0755, ?string $allowedBasePath = null): bool
    {
        if (!self::isAllowedPath((string)$target, $allowedBasePath) || !\is_dir((string)$target)) {
            return false;
        }

        return @\chmod((string)$target, self::normalizeMode($mode, 0755));
    }

    /**
     * @param   $dir_path
     */
    public static function dirExists($dir_path): bool
    {
        return \is_dir($dir_path);
    }

    private static function actionForm(string $op, string $path, string $redirectFile, string $label, int|string $mode): string
    {
        $security = self::runtimeGlobal('xoopsSecurity');
        $token    = (\is_object($security) && \method_exists($security, 'getTokenHTML')) ? $security->getTokenHTML() : '';

        return "<form action='" . self::escape($redirectFile) . "' method='post' style='display:inline;'>"
            . $token
            . "<input type='hidden' name='op' value='" . self::escape($op) . "'>"
            . "<input type='hidden' name='path' value='" . self::escape($path) . "'>"
            . "<input type='hidden' name='mode' value='" . self::escape(\decoct(self::normalizeMode($mode, 0755))) . "'>"
            . "<button type='submit' class='submit'>" . $label . '</button>'
            . '</form>';
    }

    /** Only a local path is accepted (see {@see Output::localPath()}); the fallback is validated too. */
    private static function safeRedirect(?string $redirectFile): string
    {
        return Output::localPath((string)($redirectFile ?? ''), (string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    }

    private static function isAllowedPath(string $path, ?string $allowedBasePath): bool
    {
        // A ".." segment is rejected before either containment check: resolveExistingPath()
        // canonicalises only the nearest existing ancestor, so "<base>/new/../../x" would
        // otherwise resolve inside the base while the operation targets a path outside it.
        if ('' === $path || str_contains($path, "\0") || str_contains($path, '://') || str_contains($path, '..')) {
            return false;
        }

        if (null === $allowedBasePath) {
            return self::isUnderKnownBase($path);
        }

        $base = realpath($allowedBasePath);
        $target = self::resolveExistingPath($path);

        return false !== $base
            && false !== $target
            && self::isContainedPath($target, $base);
    }

    private static function isUnderKnownBase(string $path): bool
    {
        $target = self::resolveExistingPath($path);
        if (false === $target) {
            return false;
        }

        foreach (['XOOPS_ROOT_PATH', 'XOOPS_UPLOAD_PATH'] as $constant) {
            if (!defined($constant)) {
                continue;
            }

            $base = realpath((string)constant($constant));
            if (false !== $base && self::isContainedPath($target, $base)) {
                return true;
            }
        }

        return false;
    }

    private static function isContainedPath(string $target, string $base): bool
    {
        $base = rtrim($base, DIRECTORY_SEPARATOR);

        return $target === $base || str_starts_with($target, $base . DIRECTORY_SEPARATOR);
    }

    private static function resolveExistingPath(string $path): string|false
    {
        $current = $path;
        while ('' !== $current && $current !== \dirname($current)) {
            $resolved = realpath($current);
            if (false !== $resolved) {
                return $resolved;
            }
            $current = \dirname($current);
        }

        return realpath($current);
    }

    private static function normalizeMode($mode, int $fallback): int
    {
        $mode = (int)$mode;
        $allowedModes = [0644, 0664, 0755, 0775];

        return in_array($mode, $allowedModes, true) ? $mode : $fallback;
    }

    private static function message(string $suffix, string $fallback): string
    {
        $constant = '_CO_MTOOLS_' . $suffix;
        if (!defined($constant)) {
            PackageLanguage::load('common', self::siteLanguage());
        }

        return defined($constant) ? (string)constant($constant) : $fallback;
    }

    /** @legacy-global-accessor */
    private static function runtimeGlobal(string $name): mixed
    {
        return $GLOBALS[$name] ?? null;
    }

    /** @legacy-global-accessor */
    private static function siteLanguage(): string
    {
        $config = $GLOBALS['xoopsConfig'] ?? [];

        return \is_array($config) ? (string)($config['language'] ?? 'english') : 'english';
    }

    private static function escape(string $value): string
    {
        return HtmlBuilder::escape($value);
    }
}
