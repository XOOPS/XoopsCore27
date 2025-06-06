<?php
/**
 * XOOPS main configuration file
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2016 XOOPS Project (www.xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

if (!defined('XOOPS_MAINFILE_INCLUDED')) {
    define('XOOPS_MAINFILE_INCLUDED', 1);

    // XOOPS Physical Paths

    // Physical path to the XOOPS documents (served) directory WITHOUT trailing slash
    define('XOOPS_ROOT_PATH', '');

    // For forward compatibility
    // Physical path to the XOOPS library directory WITHOUT trailing slash
    define('XOOPS_PATH', '');
    // Physical path to the XOOPS datafiles (writable) directory WITHOUT trailing slash
    define('XOOPS_VAR_PATH', '');
    // Alias of XOOPS_PATH, for compatibility, temporary solution
    define('XOOPS_TRUST_PATH', XOOPS_PATH);

    // URL Association for SSL and Protocol Compatibility
    $http = 'http://';
    if (!empty($_SERVER['HTTPS'])) {
        $http = ($_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    }
    define('XOOPS_PROT', $http);

    // XOOPS Virtual Path (URL)
    // Virtual path to your main XOOPS directory WITHOUT trailing slash
    // Example: define("XOOPS_URL", "http://url_to_xoops_directory");
    define('XOOPS_URL', 'https://');

    // XOOPS Cookie Domain to specify when creating cookies. May be blank (i.e. for IP address host),
    // full host from XOOPS_URL (i.e. www.example.com) or just the registered domain (i.e. example.com)
    // to share cookies across multiple subdomains (i.e. www.example.com and blog.example.com)
    define('XOOPS_COOKIE_DOMAIN', '');

    // Shall be handled later, don't forget!
    define('XOOPS_CHECK_PATH', 0);
    // Protect against external scripts execution if safe mode is not enabled
    if (XOOPS_CHECK_PATH) {
        if (function_exists('debug_backtrace')) {
            $xoopsScriptPath = debug_backtrace();
            if (!count($xoopsScriptPath)) {
                die('XOOPS path check: this file cannot be requested directly');
            }
            $xoopsScriptPath = $xoopsScriptPath[0]['file'];
        } else {
            $xoopsScriptPath = $_SERVER['PATH_TRANSLATED'] ?? $_SERVER['SCRIPT_FILENAME'];
        }
        if (DIRECTORY_SEPARATOR !== '/') {
            // IIS6 may double the \ chars
            $xoopsScriptPath = str_replace(strpos($xoopsScriptPath, "\\\\", 2) ? "\\\\" : DIRECTORY_SEPARATOR, '/', $xoopsScriptPath);
        }
        if (strcasecmp(substr($xoopsScriptPath, 0, strlen(XOOPS_ROOT_PATH)), str_replace(DIRECTORY_SEPARATOR, '/', XOOPS_ROOT_PATH))) {
            exit('XOOPS path check: Script is not inside XOOPS_ROOT_PATH and cannot run.');
        }
    }

    // Secure file
    require XOOPS_VAR_PATH . '/data/secure.php';

    define('XOOPS_GROUP_ADMIN', '1');
    define('XOOPS_GROUP_USERS', '2');
    define('XOOPS_GROUP_ANONYMOUS', '3');

    if (!isset($xoopsOption['nocommon']) && XOOPS_ROOT_PATH != '') {
        include XOOPS_ROOT_PATH . '/include/common.php';
    }
}
