<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.
*/

/**
 *
 * @copyright      2000-2026 XOOPS Project (https://xoops.org)
 * @license        GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author         Michael Beck <mambax7@gmail.com>
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
trait ModuleStats
{
    /**
     * @param \Xoops\ModuleTools\Common\Configurator $configurator
     * @param array                                    $moduleStats
     */
    public static function getModuleStats($configurator, $moduleStats): array
    {
        if (\count($configurator->moduleStats) > 0) {
            foreach (\array_keys($configurator->moduleStats) as $i) {
                $moduleStats[$i] = $configurator->moduleStats[$i];
            }
        }

        return $moduleStats;
    }
}
