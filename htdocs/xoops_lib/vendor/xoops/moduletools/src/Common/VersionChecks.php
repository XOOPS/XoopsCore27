<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * @copyright   2000-2026 XOOPS Project (https://xoops.org)
 * @license     GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author      mamba <mambax7@gmail.com>
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
trait VersionChecks
{
    /**
     * Verifies XOOPS version meets minimum requirements for this module
     * @static
     *
     * @param \XoopsModule|false|null $module false is the legacy "not found" value from getByDirname()
     * @param null|string $requiredVer
     * @return bool true if meets requirements, false if not
     */
    public static function checkVerXoops(\XoopsModule|false|null $module = null, $requiredVer = null): bool
    {
        $module = $module ?: \XoopsModule::getByDirname(self::consumerDirname());
        if (!$module instanceof \XoopsModule) {
            return false;
        }
        $moduleDirName = (string)$module->getVar('dirname');
        \xoops_loadLanguage('admin', $moduleDirName);
        \xoops_loadLanguage('common', $moduleDirName);

        //check for minimum XOOPS version
        $currentVer = mb_substr(\XOOPS_VERSION, 6); // get the numeric part of string
        if (null === $requiredVer) {
            $requiredVer = '' . $module->getInfo('min_xoops'); //making sure it's a string
        }
        $success = true;

        if ($module->versionCompare($currentVer, $requiredVer, '<')) {
            $success = false;
            $module->setErrors(\sprintf(self::versionCheckMessage($moduleDirName, 'ERROR_BAD_XOOPS', 'This module requires XOOPS %s+ (%s installed)'), $requiredVer, $currentVer));
        }

        return $success;
    }

    /**
     * Verifies PHP version meets minimum requirements for this module
     * @static
     * @param \XoopsModule|false|null $module false is the legacy "not found" value from getByDirname()
     *
     * @return bool true if meets requirements, false if not
     */
    public static function checkVerPhp(\XoopsModule|false|null $module = null): bool
    {
        $module = $module ?: \XoopsModule::getByDirname(self::consumerDirname());
        if (!$module instanceof \XoopsModule) {
            return false;
        }
        $moduleDirName = (string)$module->getVar('dirname');
        \xoops_loadLanguage('admin', $moduleDirName);
        \xoops_loadLanguage('common', $moduleDirName);

        // check for minimum PHP version
        $success = true;

        $verNum = \PHP_VERSION;
        $reqVer = $module->getInfo('min_php');

        if (false !== $reqVer && '' !== $reqVer && !\is_array($reqVer)) {
            if (\version_compare($verNum, $reqVer, '<')) {
                $module->setErrors(\sprintf(self::versionCheckMessage($moduleDirName, 'ERROR_BAD_PHP', 'This module requires PHP version %s+ (%s installed)'), $reqVer, $verNum));
                $success = false;
            }
        }

        return $success;
    }

    /**
     * The message for a failed check: the consumer's own `_AM_<MODULE>_*` / `_CO_<MODULE>_*`
     * constant first (legacy mTools name), then the package catalog, then a literal. The
     * package catalog is loaded here because nothing else on this path loads it, and a
     * failed check must never end in "Undefined constant".
     */
    private static function versionCheckMessage(string $moduleDirName, string $suffix, string $fallback): string
    {
        $upper = \mb_strtoupper($moduleDirName);
        foreach (['_AM_' . $upper . '_' . $suffix, '_CO_' . $upper . '_' . $suffix] as $constant) {
            if (\defined($constant)) {
                return (string) \constant($constant);
            }
        }
        \Xoops\ModuleTools\Internal\PackageLanguage::load('common', self::versionCheckLanguage());

        return \defined('_CO_MTOOLS_' . $suffix) ? (string) \constant('_CO_MTOOLS_' . $suffix) : $fallback;
    }

    /** @legacy-global-accessor */
    private static function versionCheckLanguage(): string
    {
        $config = $GLOBALS['xoopsConfig'] ?? [];

        return \is_array($config) ? (string) ($config['language'] ?? 'english') : 'english';
    }

    private static function consumerDirname(): string
    {
        $calledClass = static::class;
        if (preg_match('/^XoopsModules\\\\([^\\\\]+)/', $calledClass, $matches)) {
            return strtolower($matches[1]);
        }

        return 'mtools';
    }
}
