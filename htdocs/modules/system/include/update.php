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

/**
 * @copyright    XOOPS Project https://xoops.org/
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package
 * @since
 * @author       XOOPS Development Team, Kazumi Ono (AKA onokazu)
 */

/**
 * @param      $module
 * @param null $prev_version
 *
 * @return bool|null
 */
function xoops_module_update_system(XoopsModule $module, $prev_version = null)
{
    global $xoopsDB;
    if ($prev_version < '2.2.0-Stable') {
        //$db = XoopsDatabaseFactory::getDatabaseConnection();
        $sql = "CREATE TABLE " . $xoopsDB->prefix('') . "_menuscategory (
                category_id INT AUTO_INCREMENT PRIMARY KEY,
                category_title VARCHAR(100) NOT NULL,
                category_prefix VARCHAR(100) NOT NULL,
                category_suffix VARCHAR(100) NOT NULL,
                category_url VARCHAR(255) NULL,
                category_target TINYINT(1) DEFAULT 0,
                category_position INT DEFAULT 0,
                category_protected INT DEFAULT 0,
                category_active TINYINT(1) DEFAULT 1);";
        $xoopsDB->query($sql);
        $sql = "CREATE TABLE " . $xoopsDB->prefix('') . "_menusitems (
                items_id INT AUTO_INCREMENT PRIMARY KEY,
                items_pid INT NULL,
                items_cid INT NULL,
                items_title VARCHAR(100) NOT NULL,
                items_prefix VARCHAR(100) NOT NULL,
                items_suffix VARCHAR(100) NOT NULL,
                items_url VARCHAR(255) NULL,
                items_target TINYINT(1) DEFAULT 0,
                items_position INT DEFAULT 0,
                items_protected INT DEFAULT 0,
                items_active TINYINT(1) DEFAULT 1,
                FOREIGN KEY (items_cid) REFERENCES menuscategory(category_id) ON DELETE CASCADE,
                FOREIGN KEY (items_pid) REFERENCES menusitems(items_id) ON DELETE CASCADE);";
        // add default data
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menuscategory VALUES (1, 'MENUS_HOME', '<span class=\"fa fa-home\" ></span>', '', '/', 0, 0, 1, 1)";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menuscategory VALUES (2, 'MENUS_ADMIN', '<span class=\"fa fa-wrench fa-fw\" ></span>', '', 'admin.php', 0, 10, 1, 1)";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menuscategory VALUES (3, 'MENUS_ACCOUNT', '<span class=\"fa fa-user fa-fw\" ></span>', '', '', 0, 20, 1, 1)";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menusitems VALUES (1, 0, 3, 'MENUS_ACCOUNT_EDIT', '<span class=\"fa fa-edit fa-fw\" ></span>', '', 'user.php', 0, 1, 1, 1)";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menusitems VALUES (2, 0, 3, 'MENUS_ACCOUNT_LOGIN', '<span class=\"fa fa-sign-in fa-fw\" ></span>', '', 'user.php', 0, 2, 1, 1)";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menusitems VALUES (3, 0, 3, 'MENUS_ACCOUNT_REGISTER', '<span class=\"fa fa-sign-in fa-fw\" ></span>', '', 'register.php', 0, 2, 1, 1)";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menusitems VALUES (4, 0, 3, 'MENUS_ACCOUNT_MESSAGES', '<span class=\"fa fa-solid fa-envelope fa-fw\" ></span>', '<span class=\"badge bg-primary rounded-pill\"><{xoInboxCount}></span>', 'viewpmsg.php', 0, 3, 1, 1)";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menusitems VALUES (5, 0, 3, 'MENUS_ACCOUNT_NOTIFICATIONS', '<span class=\"fa fa-info-circle fa-fw\" ></span>', '', 'notifications.php', 0, 4, 1, 1)";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menusitems VALUES (6, 0, 3, 'MENUS_ACCOUNT_TOOLBAR', '<span class=\"fa-solid fa-screwdriver-wrench fa-fw\" ></span>', '<span id=\"xswatch-toolbar-ind\"></span>', 'javascript:xswatchToolbarToggle();', 0, 5, 1, 1)";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_menusitems VALUES (7, 0, 3, 'MENUS_ACCOUNT_LOGOUT', '<span class=\"fa fa-sign-out fa-fw\" ></span>', '', 'user.php?op=logout', 0, 5, 1, 1)";
        $xoopsDB->query($sql);
        // add permissions for category and items
        // MENUS_HOME
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 1, 1, 1, 'menus_category_view')";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 2, 1, 1, 'menus_category_view')";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 3, 1, 1, 'menus_category_view')";
        $xoopsDB->query($sql);
        // MENUS_ADMIN
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 1, 2, 1, 'menus_category_view')";
        $xoopsDB->query($sql);
        // MENUS_ACCOUNT
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 1, 3, 1, 'menus_category_view')";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 2, 3, 1, 'menus_category_view')";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 3, 3, 1, 'menus_category_view')";
        $xoopsDB->query($sql);
        // MENUS_ACCOUNT_EDIT
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 1, 1, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 2, 1, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        // MENUS_ACCOUNT_LOGIN
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 3, 2, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        // MENUS_ACCOUNT_REGISTER
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 3, 3, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        // MENUS_ACCOUNT_MESSAGES
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 1, 4, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 2, 4, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        // MENUS_ACCOUNT_NOTIFICATIONS
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 1, 5, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 2, 5, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        // MENUS_ACCOUNT_TOOLBAR
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 1, 6, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        // MENUS_ACCOUNT_LOGOUT
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 1, 7, 1, 'menus_items_view')";
        $xoopsDB->query($sql);
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_group_permission VALUES (NULL, 2, 7, 1, 'menus_items_view')";
        $xoopsDB->query($sql);

        // Add config for active menus
        $sql = "INSERT INTO " . $xoopsDB->prefix('') . "_config VALUES (NULL, 1, 0, 'active_menus', '_MI_SYSTEM_PREFERENCE_ACTIVE_MENUS', '1', '', 'hidden', 'int', 21)";
        $xoopsDB->query($sql);
    }

    // irmtfan bug fix: solve templates duplicate issue
    $ret = null;
    if ($prev_version < '2.1.1') {
        $ret = update_system_v211($module);
    }
    $errors = $module->getErrors();
    if (!empty($errors)) {
        print_r($errors);
    } else {
        $ret = true;
    }

    return $ret;
    // irmtfan bug fix: solve templates duplicate issue
}

// irmtfan bug fix: solve templates duplicate issue
/**
 * @param $module
 *
 * @return bool
 */
function update_system_v211($module)
{
    global $xoopsDB;
    $sql = 'SELECT t1.tpl_id FROM ' . $xoopsDB->prefix('tplfile') . ' t1, ' . $xoopsDB->prefix('tplfile') . ' t2 WHERE t1.tpl_refid = t2.tpl_refid AND t1.tpl_module = t2.tpl_module AND t1.tpl_tplset=t2.tpl_tplset AND t1.tpl_file = t2.tpl_file AND t1.tpl_type = t2.tpl_type AND t1.tpl_id > t2.tpl_id';
    $result = $xoopsDB->query($sql);
    if (!$xoopsDB->isResultSet($result)) {
        throw new \RuntimeException(
            \sprintf(_DB_QUERY_ERROR, $sql) . $xoopsDB->error(),
            E_USER_ERROR,
        );
    }
    $tplids = [];
    while (false !== ([$tplid] = $xoopsDB->fetchRow($result))) {
        $tplids[] = $tplid;
    }
    if (count($tplids) > 0) {
        $tplfile_handler = xoops_getHandler('tplfile');
        $duplicate_files = $tplfile_handler->getObjects(new Criteria('tpl_id', '(' . implode(',', $tplids) . ')', 'IN'));

        if (count($duplicate_files) > 0) {
            foreach (array_keys($duplicate_files) as $i) {
                $tplfile_handler->delete($duplicate_files[$i]);
            }
        }
    }
    $sql = 'SHOW INDEX FROM ' . $xoopsDB->prefix('tplfile') . " WHERE KEY_NAME = 'tpl_refid_module_set_file_type'";
    if (!$result = $xoopsDB->queryF($sql)) {
        xoops_error($xoopsDB->error() . '<br>' . $sql);

        return false;
    }
    $ret = [];
    while (false !== ($myrow = $xoopsDB->fetchArray($result))) {
        $ret[] = $myrow;
    }
    if (!empty($ret)) {
        $module->setErrors("'tpl_refid_module_set_file_type' unique index is exist. Note: check 'tplfile' table to be sure this index is UNIQUE because XOOPS CORE need it.");

        return true;
    }
    $sql = 'ALTER TABLE ' . $xoopsDB->prefix('tplfile') . ' ADD UNIQUE tpl_refid_module_set_file_type ( tpl_refid, tpl_module, tpl_tplset, tpl_file, tpl_type )';
    if (!$result = $xoopsDB->queryF($sql)) {
        xoops_error($xoopsDB->error() . '<br>' . $sql);
        $module->setErrors("'tpl_refid_module_set_file_type' unique index is not added to 'tplfile' table. Warning: do not use XOOPS until you add this unique index.");

        return false;
    }

    return true;
}
// irmtfan bug fix: solve templates duplicate issue
