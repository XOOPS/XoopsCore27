<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Module;

use Xoops\ModuleTools\Bootstrap;

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

/**
 * Shared dependency checks for modules that consume helper-host modules.
 */
final class Dependency
{
    /**
     * @return array{ok: bool, errors: list<string>, module_version: string|null}
     */
    public static function checkModule(string $dirname, string $minimumVersion, bool $requireActive = true): array
    {
        $errors = [];
        $dirname = basename($dirname);

        // `mtools` became the Core-bundled ModuleTools capability in XOOPS 2.8.
        // Keep the legacy dependency API satisfied without manufacturing a
        // module-table row or requiring an active compatibility module.
        if (in_array(strtolower($dirname), ['mtools', 'moduletools'], true)) {
            $libraryVersion = self::normalizeVersion(Bootstrap::VERSION);
            if (version_compare($libraryVersion, self::normalizeVersion($minimumVersion), '<')) {
                $errors[] = sprintf(
                    'ModuleTools %s is required; library %s is available.',
                    $minimumVersion,
                    Bootstrap::VERSION,
                );
            }

            return self::status($errors, Bootstrap::VERSION);
        }

        if (!defined('XOOPS_ROOT_PATH') || !function_exists('xoops_getHandler')) {
            return self::status(['XOOPS is not bootstrapped; load mainfile.php before checking module dependencies.'], null);
        }

        $moduleHandler = xoops_getHandler('module');
        $module = $moduleHandler->getByDirname($dirname);

        if (!$module instanceof \XoopsModule) {
            return self::status([sprintf('The %s module is not installed.', $dirname)], null);
        }

        $moduleVersion = self::normalizeVersion((string)$module->getVar('version'));

        if ($requireActive && (int)$module->getVar('isactive') !== 1) {
            $errors[] = sprintf('The %s module is installed but inactive.', $dirname);
        }

        if (version_compare($moduleVersion, self::normalizeVersion($minimumVersion), '<')) {
            $errors[] = sprintf(
                'The %s module %s is required; module %s is installed.',
                $dirname,
                $minimumVersion,
                $moduleVersion
            );
        }

        return self::status($errors, $moduleVersion);
    }

    public static function requireModule(string $dirname, string $minimumVersion, bool $requireActive = true): void
    {
        $status = self::checkModule($dirname, $minimumVersion, $requireActive);

        if (!$status['ok']) {
            throw new \RuntimeException(self::statusMessage($status));
        }
    }

    public static function statusMessage(array $status): string
    {
        return implode(' ', $status['errors'] ?? []);
    }

    /**
     * @param list<string> $errors
     *
     * @return array{ok: bool, errors: list<string>, module_version: string|null}
     */
    private static function status(array $errors, ?string $moduleVersion): array
    {
        return [
            'ok'             => [] === $errors,
            'errors'         => $errors,
            'module_version' => $moduleVersion,
        ];
    }

    private static function normalizeVersion(string $version): string
    {
        $version = trim($version);

        if ('' === $version) {
            return '0.0.0';
        }

        if (preg_match('/^\d+$/', $version) === 1 && (int)$version >= 100) {
            return number_format(((int)$version) / 100, 2, '.', '');
        }

        return preg_replace('/-(alpha|beta|rc)\d*/i', '', $version) ?? $version;
    }
}
