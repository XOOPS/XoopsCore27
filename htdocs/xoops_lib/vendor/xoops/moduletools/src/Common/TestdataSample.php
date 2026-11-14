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

use Xmf\Database\TableLoad;
use Xoops\Helpers\Utility\HtmlBuilder;
use Xoops\ModuleTools\Internal\PackageLanguage;
use Xoops\ModuleTools\Utility;

/**
 * Class TestdataSample
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
class TestdataSample
{
    public $language;
    public $xoopsConfig;
    public $moduleDirName;
    public $moduleDirNameUpper;

    /**
     * TestdataSample constructor.
     * @param $modHelper
     */
    public function __construct(public $modHelper)
    {
        global $xoopsConfig;
        $this->moduleDirName      = $this->modHelper->dirname();
        $this->moduleDirNameUpper = \mb_strtoupper($this->moduleDirName);
        $this->modHelper->loadLanguage('common');

        // The sample data lives in the consumer's testdata/<language>/; fall back to English.
        // (__DIR__ would be this library's own folder, which never holds sample data.)
        $siteLanguage   = (string) ($xoopsConfig['language'] ?? 'english');
        $this->language = 'english/';
        if (1 === \preg_match('/^[A-Za-z0-9_-]+$/', $siteLanguage) && \is_dir($this->modHelper->path('testdata/' . $siteLanguage))) {
            $this->language = $siteLanguage . '/';
        }
    }

    /** Absolute path of one sample-data file: <module>/testdata/<language>/<table>.yml */
    private function dataFile(string $table): string
    {
        return $this->modHelper->path('testdata/' . $this->language . $table . '.yml');
    }

    // XMF TableLoad for SAMPLE data

    public function loadData(): void
    {
        $this->assertAuthorized();
        $utility      = new Utility();
        $configurator = new Configurator($this->modHelper->path());
        $tables       = $this->modHelper->getModule()->getInfo('tables');

        // load module tables
        foreach ($tables as $table) {
            $tabledata = \Xmf\Yaml::readWrapped($this->dataFile((string) $table));
            \Xmf\Database\TableLoad::truncateTable($table);
            \Xmf\Database\TableLoad::loadTableFromArray($table, $tabledata);
        }

        // load permissions
        $table     = 'group_permission';
        $tabledata = \Xmf\Yaml::readWrapped($this->dataFile($table));
        $mid       = $this->modHelper->getModule()->getVar('mid');
        $this->loadTableFromArrayWithReplace($table, $tabledata, 'gperm_modid', $mid);

        //  ---  COPY test folder files ---------------
        if (\is_array($configurator->copyTestFolders) && \count($configurator->copyTestFolders) > 0) {
            $failed = [];
            foreach (\array_keys($configurator->copyTestFolders) as $i) {
                $src  = $configurator->copyTestFolders[$i][0];
                $dest = $configurator->copyTestFolders[$i][1];
                if (!$utility::rcopy($src, $dest)) {
                    $failed[] = (string) $dest;
                }
            }
            // rcopy() is webmaster-only: a module-only admin gets the rows but not the files,
            // and must be told so instead of seeing a success message.
            if ([] !== $failed) {
                PackageLanguage::load('common', \rtrim($this->language, '/'));
                \redirect_header(
                    $this->modHelper->url('admin/index.php'),
                    5,
                    \sprintf(
                        \defined('_CO_MTOOLS_LOAD_SAMPLEDATA_COPYFAIL') ? \_CO_MTOOLS_LOAD_SAMPLEDATA_COPYFAIL : 'Sample data rows were imported, but these folders could not be copied (copying files requires a webmaster): %s',
                        HtmlBuilder::escape(\implode(', ', $failed)),
                    ),
                );
            }
        }
        \redirect_header($this->modHelper->url('admin/index.php'), 1, \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'LOAD_SAMPLEDATA_SUCCESS'));
    }

    public function saveData(): void
    {
        $this->assertAuthorized();
        global $xoopsConfig;
        $tables = $this->modHelper->getModule()->getInfo('tables');

        $languageFolder = $this->modHelper->path('testdata/' . $this->language);
        if (!\file_exists($languageFolder . '/')) {
            Utility::createFolder($languageFolder . '/');
        }
        $exportFolder = $languageFolder . '/Exports-' . \date('Y-m-d-H-i-s') . '/';
        Utility::createFolder($exportFolder);

        // save module tables
        foreach ($tables as $table) {
            TableLoad::saveTableToYamlFile($table, $exportFolder . $table . '.yml');
        }

        // save permissions
        $criteria = new \CriteriaCompo();
        $criteria->add(new \Criteria('gperm_modid', $this->modHelper->getModule()->getVar('mid')));
        $skipColumns[] = 'gperm_id';
        TableLoad::saveTableToYamlFile('group_permission', $exportFolder . 'group_permission.yml', $criteria, $skipColumns);
        unset($criteria);

        \redirect_header($this->modHelper->url('admin/index.php'), 1, \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'LOAD_SAMPLEDATA_SUCCESS'));
    }

    public function exportSchema(): void
    {
        try {
            // TODO set exportSchema
            //        $migrate = new Migrate($moduleDirName);
            //        $migrate->saveCurrentSchema();
            //
            //        redirect_header('../admin/index.php', 1, constant('_CO_MTOOLS_EXPORT_SCHEMA_SUCCESS'));
        } catch (\Throwable) {
            exit(\constant('_CO_' . $this->moduleDirNameUpper . '_' . 'EXPORT_SCHEMA_ERROR'));
        }
    }

    public function clearData(): void
    {
        $this->assertAuthorized();
        // Load language files
        $this->modHelper->loadLanguage('common');
        $tables = $this->modHelper->getModule()->getInfo('tables');
        // truncate module tables
        foreach ($tables as $table) {
            \Xmf\Database\TableLoad::truncateTable($table);
        }
        \redirect_header($this->modHelper->url('admin/index.php'), 1, \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'CLEAR_SAMPLEDATA_OK'));
    }

    /**
     * Every state-changing action needs an administrator session and the one-time
     * token that TestdataButtons puts on the links; a consumer's testdata/index.php
     * dispatch cannot bypass it. redirect_header() exits.
     */
    private function assertAuthorized(): void
    {
        if (!TestdataButtons::isAuthorizedRequest()) {
            // An admin with a used or expired token sees why, not a misleading "no permission".
            $security = self::runtimeSecurity();
            $errors   = self::runtimeUserIsAdmin() && \is_object($security) ? (array) $security->getErrors() : [];
            \redirect_header(
                $this->modHelper->url('admin/index.php'),
                3,
                [] !== $errors ? \implode('<br>', $errors) : (\defined('_NOPERM') ? \_NOPERM : 'Permission denied.'),
            );
        }
    }

    /** @legacy-global-accessor */
    private static function runtimeSecurity(): mixed
    {
        return $GLOBALS['xoopsSecurity'] ?? null;
    }

    /** @legacy-global-accessor */
    private static function runtimeUserIsAdmin(): bool
    {
        return !empty($GLOBALS['xoopsUserIsAdmin']);
    }

    /**
     * loadTableFromArrayWithReplace
     *
     * @param string $table  value which should be used instead of original value of $search
     *
     * @param array  $data   array of rows to insert
     *                       Each element of the outer array represents a single table row.
     *                       Each row is an associative array in 'column' => 'value' format.
     * @param string $search name of column for which the value should be replaced
     * @param        $replace
     */
    private function loadTableFromArrayWithReplace($table, $data, $search, $replace): void
    {
        /** @var \XoopsMySQLDatabase $db */
        $db = \XoopsDatabaseFactory::getDatabaseConnection();

        $prefixedTable = $db->prefix($table);
        $count         = 0;

        $sql = 'DELETE FROM ' . $prefixedTable . ' WHERE `' . $search . '`=' . $db->quote($replace);

        $db->exec($sql);

        foreach ($data as $row) {
            $insertInto  = 'INSERT INTO ' . $prefixedTable . ' (';
            $valueClause = ' VALUES (';
            $first       = true;
            foreach ($row as $column => $value) {
                // Validate before emitting the separator, or a skipped column leaves "(`a`, , `c`)".
                if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$column)) {
                    continue;
                }
                if ($first) {
                    $first = false;
                } else {
                    $insertInto  .= ', ';
                    $valueClause .= ', ';
                }

                $insertInto .= '`' . $column . '`';
                if ($search === $column) {
                    $valueClause .= $db->quote($replace);
                } else {
                    $valueClause .= $db->quote($value);
                }
            }

            $sql = $insertInto . ') ' . $valueClause . ')';

            $result = $db->exec($sql);
            if (false !== $result) {
                ++$count;
            }
        }
    }
}
