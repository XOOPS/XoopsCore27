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
 * Class Migrate synchronize existing tables with target schema
 *
 * @category        Migrate
 * @author          Richard Griffith <richard@geekwright.com>
 * @copyright       2000-2026 XOOPS Project (https://xoops.org)
 * @license         GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 *
 * @api             Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @deprecated      since 1.4.0. Use Xmf\Database\Migrate for new code; retained through XOOPS 2.8.
 */
class Migrate extends \Xmf\Database\Migrate
{
    private $renameTables;

    /**
     * Migrate constructor.
     * @param Configurator|null $configurator
     */
    public function __construct(?Configurator $configurator = null)
    {
        if (null === $configurator) {
            throw new \InvalidArgumentException(
                'Migrate requires the consumer module Configurator; pass new Configurator($modulePath).'
            );
        }

        $this->renameTables = $configurator->renameTables;
        parent::__construct(basename($configurator->baseDir()));
    }

    /**
     * change table prefix if needed
     */
    private function changePrefix(): void
    {
        foreach ($this->renameTables as $oldName => $newName) {
            if ($this->tableHandler->useTable($oldName) && !$this->tableHandler->useTable($newName)) {
                $this->tableHandler->renameTable($oldName, $newName);
            }
        }
    }

    /**
     * Change integer IPv4 column to varchar IPv6 capable
     *
     * @param string $tableName  table to convert
     * @param string $columnName column with IP address
     */
    private function convertIPAddresses($tableName, $columnName): void
    {
        if ($this->tableHandler->useTable($tableName)) {
            $attributes = $this->tableHandler->getColumnAttributes($tableName, $columnName);
            if (str_contains($attributes, ' int(')) {
                if (!str_contains($attributes, 'unsigned')) {
                    $this->tableHandler->alterColumn($tableName, $columnName, " bigint(16) NOT NULL  DEFAULT '0' ");
                    $this->tableHandler->update($tableName, [$columnName => "4294967296 + $columnName"], "WHERE $columnName < 0", false);
                }
                $this->tableHandler->alterColumn($tableName, $columnName, " varchar(45)  NOT NULL  DEFAULT '' ");
                $this->tableHandler->update($tableName, [$columnName => "INET_NTOA($columnName)"], '', false);
            }
        }
    }

    /**
     * Move columns to another table
     */
    private function moveDoColumns(): void
    {
        //for an example, see newbb 5.0
    }

    /**
     * Perform any upfront actions before synchronizing the schema
     *
     * Some typical uses include
     *   table and column renames
     *   data conversions
     */
    protected function preSyncActions(): void
    {
        // change table prefix
        if ($this->renameTables && \is_array($this->renameTables)) {
            $this->changePrefix();
        }
        //        // columns dohtml, dosmiley, doxcode, doimage and dobr moved between tables as some point
        //        $this->moveDoColumns();
        //        // Convert IP address columns from int to readable varchar(45) for IPv6
        //        $this->convertIPAddresses('extgallery_posts', 'poster_ip');
        //        $this->convertIPAddresses('extgallery_report', 'reporter_ip');
    }
}
