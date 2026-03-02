<?php
/**
 * XOOPS Kernel Class
 *
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
 * @package      system
 * @subpackage   menus
 * @since        2.7.0
 * @author       XOOPS Development Team, Grégory Mage (AKA GregMage)
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

class XoopsMenusCategory extends XoopsObject
{
        /**
     * Tracks included language files to avoid duplicate includes
     * keyed by full path.
     *
     * @var array
     */
    private static $languageFilesIncluded = [];
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->initVar('category_id', XOBJ_DTYPE_INT, null, false);
        $this->initVar('category_title', XOBJ_DTYPE_TXTBOX, null);
        $this->initVar('category_url', XOBJ_DTYPE_TXTBOX, null);
        $this->initVar('category_position', XOBJ_DTYPE_INT, null, false);
        $this->initVar('category_active', XOBJ_DTYPE_INT, 1);
        // Load language file for menu items
        $language = $GLOBALS['xoopsConfig']['language'] ?? 'english';
        $fileinc = XOOPS_ROOT_PATH . "/modules/system/language/{$language}/menus/menus.php";
        if (!isset(self::$languageFilesIncluded[$fileinc])) {
            if (file_exists($fileinc)) {
                include_once $fileinc;
                self::$languageFilesIncluded[$fileinc] = true;
            }
        }
    }
    /**
     * Retrieve the resolved title for display.
     *
     * If the stored title is a constant name, resolves and returns its value.
     * Otherwise returns the stored title as-is. Used for front-end rendering.
     *
     * Example:
     *   - If items_title = "HOME_LABEL" and HOME_LABEL = "Accueil"
     *     returns "Accueil"
     *   - If items_title = "Custom Text"
     *     returns "Custom Text"
     *
     * @return string The resolved title value
     */
    public function getResolvedTitle()
    {
        $title = $this->getVar('category_title');
        return defined($title) ? constant($title) : $title;
    }

    /**
     * Retrieve the title for administration interface with constant reference.
     *
     * If the stored title is a constant name, returns both the resolved value
     * and the constant name in parentheses. This helps administrators identify
     * which constant is being used. Otherwise returns the stored title as-is.
     *
     * Example:
     *   - If items_title = "HOME_LABEL" and HOME_LABEL = "Accueil"
     *     returns "Accueil (HOME_LABEL)"
     *   - If items_title = "Custom Text"
     *     returns "Custom Text"
     *
     * @return string The resolved title with optional constant reference
     */
    public function getAdminTitle()
    {
        $title = $this->getVar('category_title');
        if (defined($title)) {
            return constant($title) . ' (' . $title . ')';
        } else {
            return $title;
        }
    }

    /**
     * @return mixed
     */
    public function get_new_enreg()
    {
        global $xoopsDB;
        $new_enreg = $xoopsDB->getInsertId();

        return $new_enreg;
    }

    public function getFormCat($action = false)
    {
        if ($action === false) {
            $action = $_SERVER['REQUEST_URI'];
        }
        include_once XOOPS_ROOT_PATH . '/class/xoopsformloader.php';
        //include __DIR__ . '/../include/common.php';

        //form title
        $title = $this->isNew() ? sprintf(_AM_SYSTEM_MENUS_ADDCAT) : sprintf(_AM_SYSTEM_MENUS_EDITCAT);

        $form = new XoopsThemeForm($title, 'form', $action, 'post', true);
        $form->setExtra('enctype="multipart/form-data"');

        if (!$this->isNew()) {
            $form->addElement(new XoopsFormHidden('category_id', $this->getVar('category_id')));
            $position = $this->getVar('category_position');
            $active = $this->getVar('category_active');
        } else {
            $position = 0;
            $active = 1;
        }

        // title
        $title = new XoopsFormText(_AM_SYSTEM_MENUS_TITLECAT, 'category_title', 50, 255, $this->getVar('category_title'));
        $title->setDescription(_AM_SYSTEM_MENUS_TITLECAT_DESC);
        $form->addElement($title, true);
        // url
        $url = new XoopsFormText(_AM_SYSTEM_MENUS_URLCAT, 'category_url', 50, 255, $this->getVar('category_url'));
        $url->setDescription(_AM_SYSTEM_MENUS_URLCATDESC);
        $form->addElement($url, false);

        // position
        $form->addElement(new XoopsFormText(_AM_SYSTEM_MENUS_POSITIONCAT, 'category_position', 5, 5, $position));

        // actif
        $radio = new XoopsFormRadio(_AM_SYSTEM_MENUS_ACTIVE, 'category_active', $active);
        $radio->addOption(1, _YES);
        $radio->addOption(0, _NO);
        $form->addElement($radio);

        // permission
        $permHelper = new \Xmf\Module\Helper\Permission();
        $perm = $permHelper->getGroupSelectFormForItem('menus_category_view', $this->getVar('category_id'), _AM_SYSTEM_MENUS_PERMISSION_VIEW_CATEGORY, 'menus_category_view_perms', true);
        $perm->setDescription(_AM_SYSTEM_MENUS_PERMISSION_VIEW_CATEGORY_DESC);
        $form->addElement($perm, false);

        $form->addElement(new XoopsFormHidden('op', 'savecat'));
        // submit
        $form->addElement(new XoopsFormButton('', 'submit', _SUBMIT, 'submit'));

        return $form;
    }
}
class XoopsMenusCategoryHandler extends XoopsPersistableObjectHandler
{

    /**
     * Constructor
     *
     * @param XoopsDatabase $db reference to a xoopsDB object
     */
    public function __construct($db)
    {
        // table short name, class name, key field, identifier field
        parent::__construct($db, 'menuscategory', 'XoopsMenusCategory', 'category_id', 'category_title');
    }
}