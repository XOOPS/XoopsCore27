<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/*
 Utility Class Definition

 You may not change or alter any portion of this comment or credits of
 supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit
 authors.

 This program is distributed in the hope that it will be useful, but
 WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @copyright    https://xoops.org 2000-2020 &copy; XOOPS Project
 * @author       ZySpec <zyspec@yahoo.com>
 * @author       Mamba <mambax7@gmail.com>
 */

/**
 * Class SysUtility
 *
 * Backward-compatible FACADE over the focused Common helpers. Historically this class
 * was a grab-bag; its logic now lives in {@see Text} (pure string helpers),
 * {@see Db} (database helpers, explicit handle) and {@see Output} (admin/UI output).
 * Every method below forwards to one of those, keeping its original signature so
 * existing `$utility::method()` calls — including from consumer subclasses that
 * extend this class — keep working unchanged.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
class SysUtility
{
    use VersionChecks;

    //checkVerXoops, checkVerPhp Traits

    use ServerStats;

    // getServerStats Trait

    use ModuleStats;

    // getModuleStats Trait

    use FilesManagement;

    // Files Management Trait

    //--------------- Common module methods -----------------------------

    /**
     * Access the only instance of this class
     *
     * @return object
     */
    public static function getInstance()
    {
        static $instance;
        $instance ??= new static();

        return $instance;
    }

    /**
     * Forwards to {@see Output::selectSorting()} with the CONSUMER's resolved Helper.
     *
     * @param             $text
     * @param             $form_sort
     * @param \Xmf\Module\Helper|null $helper
     */
    public static function selectSorting($text, $form_sort, $helper = null): string
    {
        return Output::selectSorting($text, $form_sort, $helper ?? self::consumerHelper());
    }

    /***************Blocks***************/

    /**
     * Forwards to {@see Db::blockAddCatSelect()}.
     *
     * @param array $cats
     */
    public static function blockAddCatSelect($cats): string
    {
        return Db::blockAddCatSelect($cats);
    }

    /**
     * Forwards to {@see Output::metaKeywords()}.
     *
     * @param $content
     */
    public static function metaKeywords($content): void
    {
        Output::metaKeywords($content);
    }

    /**
     * Forwards to {@see Output::metaDescription()}.
     *
     * @param $content
     */
    public static function metaDescription($content): void
    {
        Output::metaDescription($content);
    }

    /**
     * Forwards to {@see Db::enumerate()} using the global XOOPS database handle.
     *
     * @param $tableName
     * @param $columnName
     */
    public static function enumerate($tableName, $columnName): array
    {
        return Db::enumerate(self::runtimeDatabase(), (string)$tableName, (string)$columnName);
    }

    /**
     * Forwards to {@see Text::truncateHtml()}.
     *
     * @param string $text         String to truncate.
     * @param int    $length       Length of returned string, including ellipsis.
     * @param string $ending       Ending to be appended to the trimmed string.
     * @param bool   $exact        If false, $text will not be cut mid-word
     * @param bool   $considerHtml If true, HTML tags would be handled correctly
     *
     * @return string Trimmed string.
     */
    public static function truncateHtml($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true): string
    {
        return Text::truncateHtml((string)$text, (int)$length, (string)$ending, (bool)$exact, (bool)$considerHtml);
    }

    /**
     * Forwards to {@see Output::getEditor()} with the CONSUMER's resolved Helper.
     *
     * @param \Xmf\Module\Helper $helper
     * @param array|null         $options
     * @return \XoopsFormDhtmlTextArea|\XoopsFormEditor
     */
    public static function getEditor($helper = null, $options = null)
    {
        return Output::getEditor($helper ?? self::consumerHelper(), $options);
    }

    /**
     * Resolve the CONSUMER's module Helper (not mtools' own).
     *
     * A consumer extends this class as `XoopsModules\<Consumer>\Utility`, so late
     * static binding lets us derive its sibling `XoopsModules\<Consumer>\Helper`.
     * Callers using the base facade directly must pass a module helper explicitly;
     * ModuleTools itself has no module identity, configuration, or permissions.
     *
     * @return \Xmf\Module\Helper
     * @throws \LogicException when the consumer has no module helper
     */
    private static function consumerHelper()
    {
        $class    = static::class;
        $lastSlash = \strrpos($class, '\\');

        if (false !== $lastSlash) {
            $helperClass = \substr($class, 0, $lastSlash) . '\\Helper';

            if (\class_exists($helperClass) && \method_exists($helperClass, 'getInstance')) {
                return $helperClass::getInstance();
            }
        }

        throw new \LogicException(
            'A consumer XMF module helper is required; pass it explicitly or provide a sibling Helper class.',
        );
    }

    /**
     * Forwards to {@see Db::fieldExists()} using the global XOOPS database handle.
     *
     * @param $fieldname
     * @param $table
     */
    public static function fieldExists(string $fieldname, string $table): bool
    {
        return Db::fieldExists(self::runtimeDatabase(), $fieldname, $table);
    }

    /**
     * Forwards to {@see Db::cloneRecord()} using the global XOOPS database handle.
     *
     * @param array|string $tableName
     * @param string       $id_field
     * @param int          $id
     *
     * @return mixed
     */
    public static function cloneRecord($tableName, $id_field, $id)
    {
        return Db::cloneRecord(self::runtimeDatabase(), $tableName, (string)$id_field, (int)$id);
    }

    /**
     * Function responsible for checking if a directory exists, we can also write in and create an index.html file
     *
     * @param string $folder The full path of the directory to check
     */
    public static function prepareFolder($folder): void
    {
        if (!self::isSafeFilesystemPath((string)$folder)) {
            throw new \RuntimeException(\sprintf('Refusing unsafe directory path: %s', $folder));
        }

        if (!\is_dir($folder) && !@\mkdir($folder, 0755, true) && !\is_dir($folder)) {
            throw new \RuntimeException(\sprintf('Unable to create the %s directory', $folder));
        }

        $indexFile = rtrim((string)$folder, '/\\') . '/index.html';
        if (!is_file($indexFile)) {
            file_put_contents($indexFile, '<script>history.go(-1);</script>');
        }
    }

    /**
     * Check if dB table exists
     *
     * @deprecated Use Xmf\Database\Tables instead. Forwards to {@see Db::tableExists()}.
     *
     * @param string $tablename dB tablename with prefix
     * @return bool true if table exists
     */
    public static function tableExists(string $tablename): bool
    {
        return Db::tableExists(self::runtimeDatabase(), $tablename);
    }

    /**
     * Query and check if the result is a valid result set. Forwards to {@see Db::queryAndCheck()}.
     *
     * @param \XoopsMySQLDatabase $xoopsDB XOOPS Database
     * @param string              $sql     a valid MySQL query
     * @param int                 $limit   number of records to return
     * @param int                 $start   offset of first record to return
     *
     * @return \mysqli_result query result
     */
    public static function queryAndCheck(\XoopsMySQLDatabase $xoopsDB, string $sql, $limit = 0, $start = 0): \mysqli_result
    {
        return Db::queryAndCheck($xoopsDB, $sql, (int)$limit, (int)$start);
    }

    /**
     * QueryF and check if the result is a valid result set
     *
     * @deprecated 2.7.0 queryF() bypassed Protector's SQL inspection. For SELECTs use
     *             {@see self::queryAndCheck()} (query()); for writes/DDL call $db->exec()
     *             directly. This wrapper now delegates to {@see Db::queryFAndCheck()} and
     *             is retained only for backward compatibility.
     *
     * @param \XoopsMySQLDatabase $xoopsDB XOOPS Database
     * @param string              $sql     a valid MySQL query
     * @param int                 $limit   number of records to return
     * @param int                 $start   offset of first record to return
     *
     * @return \mysqli_result query result
     */
    public static function queryFAndCheck(\XoopsMySQLDatabase $xoopsDB, string $sql, $limit = 0, $start = 0): \mysqli_result
    {
        return Db::queryFAndCheck($xoopsDB, $sql, (int)$limit, (int)$start);
    }

    /** @legacy-global-accessor */
    private static function runtimeDatabase(): mixed
    {
        return $GLOBALS['xoopsDB'] ?? null;
    }
}
