<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Module;

use Xoops\ModuleTools\Common\Configurator;
use Xoops\ModuleTools\Common\SysUtility;
use Xoops\ModuleTools\Internal\Installation\InstallationPlan;

/**
 * Shared module install/update filesystem helper.
 *
 * Collapses the ~50–100 lines of near-identical boilerplate every consumer copies into
 * its `include/oninstall.php` / `include/onupdate.php` (create upload folders, copy
 * blank files, copy test data, drop tables, purge legacy `.html` templates, remove old
 * assets). All of it is driven by the module's {@see Configurator} (its `config/config.php`),
 * so a consumer hook shrinks to a couple of calls plus its own module-specific bits
 * (version checks, group permissions).
 *
 * Callers are responsible for the dependency/version guard and for any module-specific
 * permission setup; this class only does the repetitive Configurator-driven work.
 *
 * NOTE (document-and-defer): the step methods intentionally use `SysUtility` statics and
 * read `$GLOBALS['xoopsDB']` / `$GLOBALS['xoopsModule']`. This coupling is acceptable here
 * because install/update hooks run in a fully-bootstrapped XOOPS request where those globals
 * are guaranteed present, and the install context is not on any pure/XMF-graduation path.
 * New non-install code should depend on `Text`/`Db` (explicit handle) instead. If this class
 * is ever proposed for XMF, inject the DB handle and drop the static facade first.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
final class Installer
{
    /**
     * pre_install: make sure the upload folders exist and the module's own tables are
     * dropped first (a clean install).
     */
    public static function prepare(\XoopsModule $module, Configurator $configurator): void
    {
        self::createUploadFolders($configurator);
        self::dropModuleTables($module);
    }

    /**
     * install: create upload folders, seed blank.png + test data, and purge legacy
     * `.html` template rows. (Module-specific permissions stay in the consumer hook.)
     */
    public static function install(\XoopsModule $module, Configurator $configurator): void
    {
        self::createUploadFolders($configurator);
        self::copyBlankFiles($configurator);
        self::copyTestFolders($configurator);
        self::purgeHtmlTemplates($module);
    }

    /**
     * Create every configured upload folder (safe mkdir + index.html guard).
     */
    public static function createUploadFolders(Configurator $configurator): void
    {
        foreach (self::plan($configurator)->uploadFolders as $folder) {
            SysUtility::createFolder($folder);
        }
    }

    /**
     * Copy the module's own `assets/images/blank.png` into each configured target folder.
     */
    public static function copyBlankFiles(Configurator $configurator): void
    {
        $plan = self::plan($configurator);
        if ([] === $plan->blankFileTargets) {
            return;
        }
        foreach ($plan->blankFileTargets as $folder) {
            SysUtility::copyFile($plan->blankFileSource, $folder . '/blank.png');
        }
    }

    /**
     * Recursively copy each configured `[source, destination]` test-data folder pair.
     */
    public static function copyTestFolders(Configurator $configurator): void
    {
        foreach (self::plan($configurator)->testFolderCopies as $pair) {
            [$src, $dest] = $pair;
            if (!SysUtility::rcopy($src, $dest)) {
                \trigger_error(\sprintf('Test data folder "%s" was not copied to "%s"', $src, $dest), \E_USER_WARNING);
            }
        }
    }

    /**
     * Drop the module's own tables (declared in xoops_version `tables`). Use in pre_install.
     */
    public static function dropModuleTables(\XoopsModule $module): void
    {
        $db = self::runtimeGlobal('xoopsDB');
        foreach ((array)$module->getInfo('tables') as $table) {
            // xoops_version.php is developer-controlled, but a table name is still an identifier.
            if (1 !== \preg_match('/^[A-Za-z0-9_]+$/', (string)$table)) {
                continue;
            }
            $db->exec('DROP TABLE IF EXISTS `' . $db->prefix((string)$table) . '`');
        }
    }

    /**
     * Delete this module's legacy `.html` rows from the `tplfile` table.
     */
    public static function purgeHtmlTemplates(\XoopsModule $module): void
    {
        $db  = self::runtimeGlobal('xoopsDB');
        $sql = 'DELETE FROM ' . $db->prefix('tplfile') . ' WHERE `tpl_module` = '
            . $db->quote((string)$module->getVar('dirname', 'n'))
            . " AND `tpl_file` LIKE '%.html%'";
        $db->exec($sql);
    }

    /**
     * Update cleanup: remove configured legacy `.html` templates, old files and old folders.
     */
    public static function removeOldAssets(\XoopsModule $module, Configurator $configurator): void
    {
        $dirname = (string)$module->getVar('dirname', 'n');

        // Legacy .html templates (keep index.html).
        foreach ($configurator->templateFolders as $folder) {
            $templateFolder = self::runtimeGlobal('xoops')->path('modules/' . $dirname . $folder);
            if (!\is_dir($templateFolder)) {
                continue;
            }
            $entries = \scandir($templateFolder, \SCANDIR_SORT_NONE);
            if (false === $entries) {
                continue;
            }
            foreach (\array_diff($entries, ['.', '..']) as $entry) {
                $fileInfo = new \SplFileInfo($templateFolder . $entry);
                if (
                    'html' === $fileInfo->getExtension()
                    && 'index.html' !== $fileInfo->getFilename()
                    && \is_file($templateFolder . $entry)
                ) {
                    \unlink($templateFolder . $entry);
                }
            }
        }

        // Old files.
        foreach ($configurator->oldFiles as $file) {
            $tempFile = self::runtimeGlobal('xoops')->path('modules/' . $dirname . $file);
            if (\is_file($tempFile)) {
                \unlink($tempFile);
            }
        }

        // Old folders.
        \xoops_load('XoopsFile');
        foreach ($configurator->oldFolders as $folder) {
            $tempFolder = self::runtimeGlobal('xoops')->path('modules/' . $dirname . $folder);
            /** @var \XoopsObjectHandler $folderHandler */
            $folderHandler = \XoopsFile::getHandler('folder', $tempFolder);
            $folderHandler->delete($tempFolder);
        }
    }

    private static function plan(Configurator $configurator): InstallationPlan
    {
        return new InstallationPlan(
            uploadFolders: $configurator->uploadFolders,
            blankFileTargets: $configurator->copyBlankFiles,
            testFolderCopies: $configurator->copyTestFolders,
            blankFileSource: $configurator->baseDir() . '/assets/images/blank.png',
        );
    }

    /** @legacy-global-accessor */
    private static function runtimeGlobal(string $name): mixed
    {
        return $GLOBALS[$name] ?? null;
    }
}
