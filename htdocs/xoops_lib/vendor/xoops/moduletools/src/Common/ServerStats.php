<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

use Xoops\Helpers\Utility\HtmlBuilder;

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
trait ServerStats
{
    /**
     * serverStats()
     */
    public static function getServerStats(): string
    {
        $moduleDirName = self::consumerDirnameForStats();
        \xoops_loadLanguage('common', $moduleDirName);
        $label = static fn (string $suffix): string => self::serverStatsLabel($moduleDirName, $suffix);
        $html = '';
        //        $sql   = 'SELECT metavalue';
        //        $sql   .= ' FROM ' . $GLOBALS['xoopsDB']->prefix('wfdownloads_meta');
        //        $sql   .= " WHERE metakey='version' LIMIT 1";
        //        $query = $GLOBALS['xoopsDB']->query($sql);
        //        list($meta) = (($GLOBALS['xoopsDB']->isResultSet($query) && ($query instanceof \mysqli_result)) ? $GLOBALS['xoopsDB']->fetchRow($query) : false);
        $html .= '<fieldset>';
        $html .= "<legend style='font-weight: bold; color: #900;'>" . $label('IMAGEINFO') . '</legend>';
        $html .= "<div style='padding: 8px;'>";
        //        $html .= '<div>' . constant('_CO_MTOOLS_METAVERSION') . $meta . "</div>";
        //        $html .= "<br>";
        //        $html .= "<br>";
        $html .= '<div>' . $label('SPHPINI') . '</div>';
        $html .= '<ul>';

        if (\function_exists('gd_info')) {
            $html  .= '<li>' . $label('GDLIBSTATUS') . '<span style="color: #008000;">' . $label('GDON') . '</span>';
            $gdlib = \gd_info();
            if (!empty(($gdlib))) {
                $html .= '<li>' . $label('GDLIBVERSION') . '<b>' . self::escapeServerStat((string) $gdlib['GD Version']) . '</b>';
            }
        } else {
            $html .= '<li>' . $label('GDLIBSTATUS') . '<span style="color: #ff0000;">' . $label('GDOFF') . '</span>';
        }

        //    $safemode = ini_get('safe_mode') ? constant('_CO_MTOOLS_ON') . constant('_CO_MTOOLS_SAFEMODEPROBLEMS : constant('_CO_MTOOLS_OFF');
        //    $html .= '<li>' . constant('_CO_MTOOLS_SAFEMODESTATUS . $safemode;

        //    $registerglobals = (!ini_get('register_globals')) ? "<span style=\"color: #008000;\">" . constant('_CO_MTOOLS_OFF') . '</span>' : "<span style=\"color: #ff0000;\">" . constant('_CO_MTOOLS_ON') . '</span>';
        //    $html .= '<li>' . constant('_CO_MTOOLS_REGISTERGLOBALS . $registerglobals;

        $downloads = \ini_get('file_uploads') ? '<span style="color: #008000;">' . $label('ON') . '</span>' : '<span style="color: #ff0000;">' . $label('OFF') . '</span>';
        $html      .= '<li>' . $label('SERVERUPLOADSTATUS') . $downloads;

        $html .= '<li>' . $label('MAXUPLOADSIZE') . ' <b><span style="color: #0000ff;">' . self::escapeServerStat((string) \ini_get('upload_max_filesize')) . '</span></b>';
        $html .= '<li>' . $label('MAXPOSTSIZE') . ' <b><span style="color: #0000ff;">' . self::escapeServerStat((string) \ini_get('post_max_size')) . '</span></b>';
        $html .= '<li>' . $label('MEMORYLIMIT') . ' <b><span style="color: #0000ff;">' . self::escapeServerStat((string) \ini_get('memory_limit')) . '</span></b>';
        $html .= '</ul>';
        $html .= '<ul>';
        $html .= '<li>' . $label('SERVERPATH') . ' <b>' . self::escapeServerStat((string) XOOPS_ROOT_PATH) . '</b>';
        $html .= '</ul>';
        $html .= '<br>';
        $html .= $label('UPLOADPATHDSC');
        $html .= '</div>';
        $html .= '</fieldset><br>';

        return $html;
    }

    private static function serverStatsLabel(string $moduleDirName, string $suffix): string
    {
        $moduleKey = \mb_strtoupper((string) \preg_replace('/[^A-Za-z0-9]+/', '_', $moduleDirName));
        $consumerConstant = '_CO_' . $moduleKey . '_' . $suffix;
        if (\defined($consumerConstant)) {
            return (string) \constant($consumerConstant);
        }

        \Xoops\ModuleTools\Internal\PackageLanguage::load('common');

        $fallbackConstant = '_CO_MTOOLS_' . $suffix;

        return \defined($fallbackConstant) ? (string) \constant($fallbackConstant) : $suffix;
    }

    private static function escapeServerStat(string $value): string
    {
        return HtmlBuilder::escape($value);
    }

    /** @legacy-global-accessor */
    private static function consumerDirnameForStats(): string
    {
        $calledClass = static::class;
        if (preg_match('/^XoopsModules\\\\([^\\\\]+)/', $calledClass, $matches)) {
            return strtolower($matches[1]);
        }

        return isset($GLOBALS['xoopsModule']) && $GLOBALS['xoopsModule'] instanceof \XoopsModule
            ? (string)$GLOBALS['xoopsModule']->getVar('dirname')
            : 'mtools';
    }
}
