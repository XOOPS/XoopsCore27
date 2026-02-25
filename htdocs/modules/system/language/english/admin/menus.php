<?php
/**
 * @copyright       (c) 2000-2025 XOOPS Project (www.xoops.org)
 * @license         GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * _LANGCODE    en
 * _CHARSET     UTF-8
 */
// Navigation
define('_AM_SYSTEM_MENUS_NAV_MAIN', 'Menus Management');
define('_AM_SYSTEM_MENUS_NAV_CATEGORY', 'Category Management');

// Tips
define('_AM_SYSTEM_MENUS_NAV_TIPS', '
<ul>
<li>Manage Xoops menus</li>
</ul>');
// Main
define('_AM_SYSTEM_MENUS_ADDCAT', 'Add Category');
define('_AM_SYSTEM_MENUS_ADDITEM', 'Adding a submenu item');
define('_AM_SYSTEM_MENUS_EDITCAT', 'Edit Category');
define('_AM_SYSTEM_MENUS_DELCAT', 'Delete Category');
define('_AM_SYSTEM_MENUS_DELITEM', 'Delete a submenu item');
define('_AM_SYSTEM_MENUS_EDITITEM', 'Edit a submenu item');
define('_AM_SYSTEM_MENUS_LISTCAT', 'List Categories');
define('_AM_SYSTEM_MENUS_LISTITEM', 'List items');
define('_AM_SYSTEM_MENUS_TITLECAT', 'Name of the menu category');
define('_AM_SYSTEM_MENUS_TITLEITEM', 'Name of the submenu item');
define('_AM_SYSTEM_MENUS_URLCAT', 'URL of the menu category');
define('_AM_SYSTEM_MENUS_URLCATDESC', 'Optional — Only if you want the category title to be a link.');
define('_AM_SYSTEM_MENUS_URLITEM', 'URL of the submenu item');
define('_AM_SYSTEM_MENUS_POSITIONCAT', 'Position of the menu category');
define('_AM_SYSTEM_MENUS_POSITIONITEM', 'Position of the submenu item');
define('_AM_SYSTEM_MENUS_ACTIVE', 'Active');
define('_AM_SYSTEM_MENUS_ACTIVE_YES', 'Enabled');
define('_AM_SYSTEM_MENUS_ACTIVE_NO', 'Disabled');
define('_AM_SYSTEM_MENUS_PID', 'Upper level menu');
define('_AM_SYSTEM_MENUS_ERROR_NOCATEGORY', 'There are no menu categories. You must create one before adding menus.');
define('_AM_SYSTEM_MENUS_ERROR_NOITEM', 'There are no submenu item.');
define('_AM_SYSTEM_MENUS_SUREDELCAT', 'Are you sure you want to delete this menu category "%s" with its submenu items?');
define('_AM_SYSTEM_MENUS_SUREDELITEM', 'Are you sure you want to delete this submenu item "%s"? avec the following submenu items?');
define('_AM_SYSTEM_MENUS_ERROR_ITEMPARENT', 'You cannot select a menu as its own parent.');
define('_AM_SYSTEM_MENUS_ERROR_ITEMDISABLE', 'You cannot delete a menu that is disabled. Please enable the menu first, then try deleting it again.');
define('_AM_SYSTEM_MENUS_ERROR_ITEMEDIT', 'You cannot edit a menu that is disabled. Please enable the menu first, then try editing it again.');
define('_AM_SYSTEM_MENUS_ERROR_PARENTINACTIVE', 'You cannot modify this item while its parent is inactive!');