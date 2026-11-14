<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/**
 * @category     Module
 * @package      mtools
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @copyright    https://xoops.org 2000-2026 &copy; XOOPS Project
 * @author       Mamba <mambax7@gmail.com>
 */

/**
 * Contract for the database helpers extracted from SysUtility.
 *
 * Unlike the legacy SysUtility methods (which read `$GLOBALS['xoopsDB']`), every
 * method here takes the database handle as an EXPLICIT parameter, so the class has no
 * hidden global dependency and is ready to graduate to XMF. {@see SysUtility} keeps
 * the old globals-reading signatures and forwards to these.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
interface DbInterface
{
    /**
     * Run a SELECT and return a validated result set, or throw on failure.
     *
     * @param \XoopsMySQLDatabase $db    XOOPS Database
     * @param string              $sql   a valid MySQL query
     * @param int                 $limit number of records to return
     * @param int                 $start offset of first record to return
     *
     * @return \mysqli_result query result
     */
    public static function queryAndCheck(\XoopsMySQLDatabase $db, string $sql, int $limit = 0, int $start = 0): \mysqli_result;

    /**
     * @param string $fieldname column name
     * @param string $table     table name (already prefixed)
     */
    public static function fieldExists(\XoopsMySQLDatabase $db, string $fieldname, string $table): bool;

    /**
     * Duplicate a single record and return the new auto-increment id (or false).
     *
     * @param array|string $tableName unprefixed table name
     * @param string       $idField   auto-increment key field
     * @param int          $id        id of the record to clone
     *
     * @return mixed new id on success, false otherwise
     */
    public static function cloneRecord(\XoopsMySQLDatabase $db, $tableName, string $idField, int $id);

    /**
     * Read the allowed values of an ENUM/SET column.
     *
     * @param string $tableName  unprefixed table name
     * @param string $columnName column to inspect
     */
    public static function enumerate(\XoopsMySQLDatabase $db, string $tableName, string $columnName): array;

    /**
     * Build a parenthesised list of category ids for a SQL IN(...) fragment.
     *
     * Pure string construction (no database access); lives here because the output
     * is a SQL fragment consumed by block queries.
     *
     * @param array $cats list of category ids
     */
    public static function blockAddCatSelect($cats): string;
}
