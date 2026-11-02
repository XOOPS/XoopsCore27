<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Smarty plugin
 *
 * Fetches templates from a database
 *
 * @copyright       2000-2026 XOOPS Project (https://xoops.org)
 * @license         GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 */
class Smarty_Resource_Db extends Smarty_Resource_Custom
{

    /**
     * Fetch a template and its modification time from database
     *
     * @param  string  $name   template name
     * @param  string  $source template source
     * @param  int     $mtime  template modification timestamp (epoch)
     *
     * @return void
     */
    protected function fetch($name, &$source, &$mtime)
    {
        $tpl = $this->dbTplInfo($name);
        if (is_object($tpl)) {
            /** @var XoopsTplFile $tpl */
            $source = $tpl->getVar('tpl_source', 'n');
            $mtime = $tpl->getVar('tpl_lastmodified', 'n');
        } else {
            $stat = ('' !== $tpl) ? stat($tpl) : false;

            // Did we fail to get stat information?
            if ($stat) {
                $mtime = $stat['mtime'];
                $filesize = $stat['size'];
                $fp = fopen($tpl, 'r');
                $source = ($filesize > 0) ? fread($fp, $filesize) : '';
                fclose($fp);
            } else {
                $source = null;
                $mtime = null;
            }
        }
    }

    /**
     * Get template info from db, or file name if available
     *
     * @param string $tpl_name template name
     *
     * @return XoopsTplFile|string tpl object from database or absolute file name path
     */
    private function dbTplInfo($tpl_name)
    {
        static $cache = [];
        global $xoopsConfig;
        // $xoops = Xoops::getInstance();

        if (isset($cache[$tpl_name])) {
            return $cache[$tpl_name];
        }
        $tplset = $xoopsConfig['template_set'];
        $theme = $xoopsConfig['theme_set'] ?? 'default';
        $tplfile_handler = xoops_getHandler('tplfile'); // $xoops->getHandlerTplFile();
        // If we're not using the "default" template set, then get the templates from the DB
        if ($tplset !== 'default') {
            $tplobj = $tplfile_handler->find($tplset, null, null, null, $tpl_name, true);
            if (count($tplobj)) {
                return $cache[$tpl_name] = $tplobj[0];
            }
        }
        // If we'using the default tplset, get the template from the filesystem
        $tplobj = $tplfile_handler->find('default', null, null, null, $tpl_name, true);

        if (!count($tplobj)) {
            // An unregistered name is read as a file only when it is a real
            // template file inside the XOOPS trees: never a stream URI
            // (file://, php://), a file outside them, or a PHP file such as
            // mainfile.php, which a stored template name could otherwise name.
            return $cache[$tpl_name] = self::templateFile((string) $tpl_name);
        }
        /** @var XoopsTplFile $tplobj */
        $tplobj = $tplobj[0];
        $module = $tplobj->getVar('tpl_module', 'n');
        $type = $tplobj->getVar('tpl_type', 'n');
        // Construct template path
        switch ($type) {
            case 'block':
                $directory = XOOPS_ROOT_PATH . '/themes'; //\XoopsBaseConfig::get('themes-path');
                $path = 'blocks/';
                break;
            case 'admin':
                $theme = $xoopsConfig['cpanel'] ?? 'default';
                $directory = XOOPS_ROOT_PATH . '/modules/system/themes'; //\XoopsBaseConfig::get('adminthemes-path');
                $path = 'admin/';
                break;
            default:
                $directory = XOOPS_ROOT_PATH . '/themes'; //\XoopsBaseConfig::get('themes-path');
                $path = '';
                if (class_exists('XoopsSystemCpanel', false)) {
                    $directory = XOOPS_ADMINTHEME_PATH;
                    $theme     = $xoopsConfig['cpanel'] ?? 'default';
                }
                break;
        }
        // First, check for an overloaded version within the theme folder
        $filepath = $directory . "/{$theme}/modules/{$module}/{$path}{$tpl_name}";
        if (!file_exists($filepath)) {
            // If no custom version exists, get the tpl from its default location
            $filepath = XOOPS_ROOT_PATH . "/modules/{$module}/templates/{$path}{$tpl_name}";
            if (!file_exists($filepath)) {
                return $cache[$tpl_name] = $tplobj ;
            }
        }
        return $cache[$tpl_name] = $filepath;
    }

    /**
     * The absolute path of an unregistered template name, or '' (no source).
     *
     * @param string $name template name as given to the db: resource
     * @return string
     */
    private static function templateFile(string $name): string
    {
        if (str_contains($name, '://') || !preg_match('/\.(tpl|html)\z/i', $name)) {
            return '';
        }
        $real = realpath($name);
        // The resolved file too: a .tpl symlink to mainfile.php must not be read.
        if (false === $real || !is_file($real) || !preg_match('/\.(tpl|html)\z/i', $real)) {
            return '';
        }
        $roots = [XOOPS_ROOT_PATH];
        foreach (['XOOPS_PATH', 'XOOPS_VAR_PATH'] as $constant) {
            if (defined($constant)) {
                $roots[] = constant($constant);
            }
        }
        foreach ($roots as $root) {
            $root = realpath((string) $root);
            if (false !== $root && str_starts_with($real, rtrim($root, '\\/') . DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        return '';
    }
}
