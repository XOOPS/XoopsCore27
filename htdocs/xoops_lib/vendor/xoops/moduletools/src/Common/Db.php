<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/**
 * @category     Module
 * @package      mtools
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @copyright    https://xoops.org 2000-2026 &copy; XOOPS Project
 * @author       ZySpec <zyspec@yahoo.com>
 * @author       Mamba <mambax7@gmail.com>
 */

/**
 * Database helpers extracted from {@see SysUtility}.
 *
 * Every method takes the database handle EXPLICITLY (no `$GLOBALS['xoopsDB']` read),
 * which removes the hidden global dependency and makes this an XMF candidate.
 * {@see SysUtility} retains the legacy globals-reading signatures and forwards here.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
final class Db implements DbInterface
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
    public static function queryAndCheck(\XoopsMySQLDatabase $db, string $sql, int $limit = 0, int $start = 0): \mysqli_result
    {
        $result = $db->query($sql, $limit, $start);

        if (!$db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            throw new \RuntimeException(
                \sprintf(\_DB_QUERY_ERROR, $sql) . $db->error(),
                \E_USER_ERROR
            );
        }

        return $result;
    }

    /**
     * QueryF and check if the result is a valid result set.
     *
     * @deprecated 2.7.0 queryF() bypassed Protector's SQL inspection. For SELECTs use
     *             {@see self::queryAndCheck()} (query()); for writes/DDL call $db->exec()
     *             directly. Retained for backward compatibility only.
     *
     * @param \XoopsMySQLDatabase $db    XOOPS Database
     * @param string              $sql   a valid MySQL query
     * @param int                 $limit number of records to return
     * @param int                 $start offset of first record to return
     *
     * @return \mysqli_result query result
     */
    public static function queryFAndCheck(\XoopsMySQLDatabase $db, string $sql, int $limit = 0, int $start = 0): \mysqli_result
    {
        return self::queryAndCheck($db, $sql, $limit, $start);
    }

    /**
     * @param string $fieldname column name
     * @param string $table     table name (already prefixed)
     */
    public static function fieldExists(\XoopsMySQLDatabase $db, string $fieldname, string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $fieldname) || !preg_match('/^[A-Za-z0-9_`]+$/', $table)) {
            return false;
        }

        $sql    = 'SHOW COLUMNS FROM ' . $table . ' LIKE ' . $db->quote($fieldname);
        $result = self::queryAndCheck($db, $sql);

        return ((($db->isResultSet($result) && ($result instanceof \mysqli_result)) ? $db->getRowsNum($result) : 0) > 0);
    }

    /**
     * Duplicate a single record and return the new auto-increment id (or false).
     *
     * @param string|array<int|string, mixed> $tableName unprefixed table name
     * @param string                          $idField   auto-increment key field
     * @param int                             $id        id of the record to clone
     *
     * @return mixed new id on success, false otherwise
     */
    public static function cloneRecord(\XoopsMySQLDatabase $db, $tableName, string $idField, int $id)
    {
        $new_id = false;
        if (
            !preg_match('/^[A-Za-z0-9_]+$/', (string)$tableName)
            || !preg_match('/^[A-Za-z0-9_]+$/', (string)$idField)
        ) {
            return false;
        }

        $table  = $db->prefix($tableName);
        // copy content of the record you wish to clone
        $sql    = "SELECT * FROM $table WHERE `$idField`=" . $db->quote((string)$id);
        $tempTable = null;
        $result    = $db->query($sql);
        if ($db->isResultSet($result) && $result instanceof \mysqli_result) {
            $tempTable = $db->fetchArray($result);
        }
        if (!\is_array($tempTable) || [] === $tempTable) {
            \trigger_error($db->error());

            return false;
        }
        // set the auto-incremented id's value to blank.
        unset($tempTable[$idField]);
        // insert cloned copy of the original  record
        $columns = array_map(static fn ($column): string => '`' . str_replace('`', '``', (string)$column) . '`', array_keys($tempTable));
        $values  = array_map(static fn ($value): string => null === $value ? 'NULL' : $db->quote((string)$value), array_values($tempTable));
        $sql     = "INSERT INTO $table (" . \implode(', ', $columns) . ') VALUES (' . \implode(', ', $values) . ')';
        $result  = $db->exec($sql);
        if (!$result) {
            \trigger_error(\sprintf(\_DB_QUERY_ERROR, $sql) . $db->error(), \E_USER_ERROR);
        }
        // Return the new id
        $new_id = $db->getInsertId();

        return $new_id;
    }

    /**
     * Read the allowed values of an ENUM/SET column.
     *
     * @param string $tableName  unprefixed table name
     * @param string $columnName column to inspect
     *
     * @return list<string>
     */
    public static function enumerate(\XoopsMySQLDatabase $db, string $tableName, string $columnName): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) {
            return [];
        }

        $table = $db->prefix($tableName);

        // TABLE_NAME / COLUMN_NAME are string columns in INFORMATION_SCHEMA: bind them as
        // properly escaped string literals via quote() rather than interpolating raw values.
        $sql    = 'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '
            . $db->quote($table) . ' AND COLUMN_NAME = ' . $db->quote($columnName);
        $result = $db->query($sql);
        if (!$db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            \trigger_error(\sprintf(\_DB_QUERY_ERROR, $sql) . $db->error(), \E_USER_ERROR);
        }

        $row = (($db->isResultSet($result) && ($result instanceof \mysqli_result)) ? $db->fetchBoth($result) : false);
        if (false === $row) {
            return [];
        }

        return self::enumValues((string) $row['COLUMN_TYPE']);
    }

    /**
     * The allowed values of an INFORMATION_SCHEMA COLUMN_TYPE such as enum('a','b') or set('x','y').
     * A doubled quote inside a value is unescaped; anything that is not an ENUM/SET yields [].
     *
     * @return list<string>
     */
    public static function enumValues(string $columnType): array
    {
        if (!preg_match('/^(?:enum|set)\((.*)\)$/is', trim($columnType), $match)) {
            return [];
        }
        preg_match_all("/'((?:[^']|'')*)'/", $match[1], $values);

        return array_map(static fn (string $value): string => str_replace("''", "'", $value), $values[1]);
    }

    /**
     * Check if a dB table exists.
     *
     * @deprecated Use Xmf\Database\Tables instead.
     *
     * @param string $tablename dB tablename with prefix
     * @return bool true if table exists
     */
    public static function tableExists(\XoopsMySQLDatabase $db, string $tablename): bool
    {
        $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        \trigger_error(__FUNCTION__ . " is deprecated, called from {$trace[0]['file']} line {$trace[0]['line']}");
        $logger = self::runtimeLogger();
        if (is_object($logger) && method_exists($logger, 'addDeprecated')) {
            $logger->addDeprecated(
                \basename(\dirname(__DIR__, 2)) . ' Module: ' . __FUNCTION__ . ' function is deprecated, please use Xmf\Database\Tables method(s) instead.' . " Called from {$trace[0]['file']}line {$trace[0]['line']}"
            );
        }
        $sql    = 'SHOW TABLES LIKE ' . $db->quote($tablename);
        $result = self::queryAndCheck($db, $sql);

        return $db->isResultSet($result) && ($result instanceof \mysqli_result) && $db->getRowsNum($result) > 0;
    }

    /**
     * Build a parenthesised list of category ids for a SQL IN(...) fragment.
     *
     * Pure string construction (no database access). Every id is cast to int, so the
     * returned fragment is always safe to interpolate into a WHERE ... IN(...) clause.
     *
     * @param array $cats list of category ids
     */
    public static function blockAddCatSelect($cats): string
    {
        if (!\is_array($cats) || empty($cats)) {
            return '';
        }

        return '(' . \implode(',', \array_map(\intval(...), $cats)) . ')';
    }

    /** @legacy-global-accessor */
    private static function runtimeLogger(): mixed
    {
        return $GLOBALS['xoopsLogger'] ?? null;
    }
}
