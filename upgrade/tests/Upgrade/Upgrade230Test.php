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

declare(strict_types=1);

namespace Xoops\Upgrade\Tests\Upgrade;

use mysqli_result;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Upgrade_230;
use Xoops\Upgrade\UpgradeControl;
use XoopsMySQLDatabase;

require_once dirname(__DIR__) . '/fixtures/XoopsMySQLDatabaseStub.php';
require_once dirname(__DIR__, 2) . '/upd-2.0.18-to-2.3.0/index.php';

/**
 * apply_config() kept the result of its welcome_type query in the variable it
 * returns, so on a site that already had that setting the task ended with a
 * TypeError instead of a result. convert_db() read the table list by
 * destructuring each fetched row inside the loop condition, which PHP 8.5
 * reports with a warning once the rows run out.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class Upgrade230Test extends TestCase
{
    /** @var list<string> */
    private array $executed = [];

    /** @var mixed */
    private $savedConfig;

    protected function setUp(): void
    {
        if (!class_exists(mysqli_result::class)) {
            self::markTestSkipped('The mysqli extension is required for database result mocks.');
        }
        $this->executed    = [];
        $this->savedConfig = $GLOBALS['xoopsConfig'] ?? null;
    }

    protected function tearDown(): void
    {
        if (null === $this->savedConfig) {
            unset($GLOBALS['xoopsConfig']);
        } else {
            $GLOBALS['xoopsConfig'] = $this->savedConfig;
        }
    }

    #[Test]
    public function applyConfigSucceedsWhenBothSettingsAreAlreadyThere(): void
    {
        $GLOBALS['xoopsConfig'] = ['cpanel' => 'default'];

        self::assertTrue($this->patch([[1]])->apply_config());
        self::assertSame([], $this->executed);
    }

    #[Test]
    public function applyConfigFailsWhenTheCpanelSettingCannotBeAdded(): void
    {
        $GLOBALS['xoopsConfig'] = [];

        self::assertFalse($this->patch([[1]], [false])->apply_config());
        self::assertCount(1, $this->executed);
    }

    #[Test]
    public function aFailedCpanelInsertIsNotHiddenByTheWelcomeTypeInsertsThatFollow(): void
    {
        $GLOBALS['xoopsConfig'] = [];

        // no welcome_type yet: the cpanel insert fails, the two welcome_type inserts succeed
        self::assertFalse($this->patch([[0]], [false, true, true])->apply_config());
        self::assertCount(3, $this->executed);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function convertDbReadsEveryTableNameWithoutAWarning(): void
    {
        // convert_db() names the database and its prefix by constant
        define('XOOPS_DB_NAME', 'xoops_test');
        define('XOOPS_DB_PREFIX', 'xoops');
        $patch = $this->patch([['xoops_config'], ['xoops_users']]);

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        });
        try {
            self::assertTrue($patch->convert_db('utf8mb4', 'utf8mb4_unicode_ci'));
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertSame(['xoops_config', 'xoops_users'], $patch->converted);
    }

    #[Test]
    public function convertTableKeepsEveryColumnDefaultIncludingAnEmptyString(): void
    {
        $describe = [
            ['Field' => 'uname', 'Type' => 'varchar(25)', 'Null' => 'NO', 'Default' => ''],
            ['Field' => 'level', 'Type' => "enum('0','1')", 'Null' => 'NO', 'Default' => '0'],
            ['Field' => 'title', 'Type' => 'varchar(60)', 'Null' => 'NO', 'Default' => "it's"],
            ['Field' => 'bio', 'Type' => 'text', 'Null' => 'YES', 'Default' => null],
        ];
        $patch = new Upgrade_230(
            $this->database([], [], ['DESCRIBE' => $describe]),
            $this->createMock(UpgradeControl::class),
        );

        $queries = $patch->convert_table(['xoops_users'], 'utf8mb4', 'utf8mb4_unicode_ci');

        $charset = ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ';
        self::assertContains("ALTER TABLE `xoops_users` MODIFY `uname` varchar(25){$charset}DEFAULT '' NOT NULL", $queries);
        self::assertContains("ALTER TABLE `xoops_users` MODIFY `level` enum('0','1'){$charset}DEFAULT '0' NOT NULL", $queries);
        self::assertContains("ALTER TABLE `xoops_users` MODIFY `title` varchar(60){$charset}DEFAULT 'it\\'s' NOT NULL", $queries);
        self::assertContains("ALTER TABLE `xoops_users` MODIFY `bio` text{$charset}NULL", $queries);
        // The binary pass keeps the default too, or NOT NULL columns lose it there.
        self::assertContains("ALTER TABLE `xoops_users` MODIFY `uname` varbinary(25) DEFAULT '' NOT NULL", $queries);
        self::assertSame($queries, $this->executed);
    }

    /**
     * @param list<list<int|string>> $rows       rows every SELECT returns
     * @param list<bool>             $execResults what each write returns in turn; true once the list is used up
     */
    private function patch(array $rows, array $execResults = []): Upgrade_230
    {
        return new class ($this->database($rows, $execResults), $this->createMock(UpgradeControl::class)) extends Upgrade_230 {
            /** @var list<string> */
            public array $converted = [];

            public function convert_table($tables, $charset, $collation)
            {
                $this->converted = $tables;

                return [];
            }
        };
    }

    /**
     * @param list<array<int|string, int|string|null>>               $rows       rows a query returns by default
     * @param list<bool>                                             $execResults what each write returns in turn; true once the list is used up
     * @param array<string, list<array<int|string, int|string|null>>> $rowsByVerb rows for queries starting with that word (DESCRIBE, SHOW, ...)
     */
    private function database(array $rows, array $execResults = [], array $rowsByVerb = []): XoopsMySQLDatabase
    {
        return new class ($rows, $execResults, $rowsByVerb, $this->executed) extends XoopsMySQLDatabase {
            /** @var array<int, list<array<int|string, int|string|null>>> */
            private array $pending = [];

            /**
             * @param list<array<int|string, int|string|null>>               $rows
             * @param list<bool>                                             $execResults
             * @param array<string, list<array<int|string, int|string|null>>> $rowsByVerb
             * @param list<string>                                           $executed
             */
            public function __construct(
                private array $rows,
                private array $execResults,
                private array $rowsByVerb,
                private array &$executed,
            ) {
            }

            public function query($sql, $limit = 0, $start = 0)
            {
                $result = (new \ReflectionClass(mysqli_result::class))->newInstanceWithoutConstructor();
                $this->pending[spl_object_id($result)] = $this->rowsByVerb[strtok((string) $sql, ' ')] ?? $this->rows;

                return $result;
            }

            public function isResultSet($result): bool
            {
                return $result instanceof mysqli_result;
            }

            public function fetchRow($result)
            {
                return array_shift($this->pending[spl_object_id($result)]) ?? false;
            }

            public function fetchArray($result)
            {
                return array_shift($this->pending[spl_object_id($result)]) ?? false;
            }

            public function quote($value): string
            {
                return "'" . addslashes((string) $value) . "'";
            }

            public function exec($sql): bool
            {
                $this->executed[] = (string) $sql;

                return array_shift($this->execResults) ?? true;
            }
        };
    }
}
