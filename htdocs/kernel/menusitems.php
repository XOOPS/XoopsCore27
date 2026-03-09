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

class XoopsMenusItems extends XoopsObject
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
        $this->initVar('items_id', XOBJ_DTYPE_INT, null, false);
        $this->initVar('items_pid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('items_cid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('items_title', XOBJ_DTYPE_TXTBOX, null);
        $this->initVar('items_prefix', XOBJ_DTYPE_TXTAREA);
        $this->initVar('items_suffix', XOBJ_DTYPE_TXTAREA);
        $this->initVar('items_url', XOBJ_DTYPE_TXTBOX, null);
        $this->initVar('items_target', XOBJ_DTYPE_INT, 0);
        $this->initVar('items_position', XOBJ_DTYPE_INT, null, false);
        $this->initVar('items_protected', XOBJ_DTYPE_INT, 0);
        $this->initVar('items_active', XOBJ_DTYPE_INT, 1);
        // use html
		$this->initVar('dohtml', XOBJ_DTYPE_INT, 1, false);
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
        $title = $this->getVar('items_title');
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
        $title = $this->getVar('items_title');
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

    public function getFormItems($category_id, $action = false)
    {
        if ($action === false) {
            $action = $_SERVER['REQUEST_URI'];
        }
        include_once XOOPS_ROOT_PATH . '/class/xoopsformloader.php';
        //include __DIR__ . '/../include/common.php';

        //form title
        $title = $this->isNew() ? sprintf(_AM_SYSTEM_MENUS_ADDITEM) : sprintf(_AM_SYSTEM_MENUS_EDITITEM);

        $form = new XoopsThemeForm($title, 'form', $action, 'post', true);
        $form->setExtra('enctype="multipart/form-data"');

        if (!$this->isNew()) {
            $form->addElement(new XoopsFormHidden('items_id', $this->getVar('items_id')));
            $position = $this->getVar('items_position');
            $active = $this->getVar('items_active');
        } else {
            $position = 0;
            $active = 1;
        }

        // category
        $menuscategoryHandler = xoops_getHandler('menuscategory');
        $category = $menuscategoryHandler->get($category_id);
        $form->addElement(new XoopsFormLabel(_AM_SYSTEM_MENUS_TITLECAT, $category->getVar('category_title')));
        $form->addElement(new XoopsFormHidden('items_cid', $category_id));

        // Tree
        $criteria = new CriteriaCompo();
		$criteria->add(new Criteria('items_cid', $category_id));
		$criteria->add(new Criteria('items_active', 1));
        $criteria->setSort('items_position ASC, items_title');
        $criteria->setOrder('ASC');
        $menusitemsHandler = xoops_getHandler('menusitems');
        $item_arr = $menusitemsHandler->getall($criteria);
        // Use admin-friendly title (resolved value + constant name) for select labels
        foreach ($item_arr as $key => $obj) {
            if (is_object($obj) && method_exists($obj, 'getAdminTitle')) {
                $obj->setVar('items_title', $obj->getAdminTitle());
                $item_arr[$key] = $obj;
            }
        }
        include_once $GLOBALS['xoops']->path('class/tree.php');
        $myTree = new XoopsObjectTree($item_arr, 'items_id', 'items_pid');
        $suparticle = $myTree->makeSelectElement('items_pid', 'items_title', '--', $this->getVar('items_pid'), true, 0, '', _AM_SYSTEM_MENUS_PID);
        $form->addElement($suparticle, false);

        // title
        $title = new XoopsFormText(_AM_SYSTEM_MENUS_TITLEITEM, 'items_title', 50, 255, $this->getVar('items_title'));
        $title->setDescription(_AM_SYSTEM_MENUS_TITLEITEM_DESC);
        $form->addElement($title, true);
        // prefix
        $editor_configs = array(
            'name' => 'items_prefix',
            'value' => $this->getVar('items_prefix'),
            'rows' => 1,
            'cols' => 50,
            'width' => '100%',
            'height' => '200px',
            'editor' => 'Plain Text'
        );
        $prefix = new XoopsFormEditor(_AM_SYSTEM_MENUS_PREFIXITEM, 'items_prefix', $editor_configs, false, 'textarea');
        $prefix->setDescription(_AM_SYSTEM_MENUS_PREFIXITEM_DESC);
        $form->addElement($prefix, false);
        // suffix
        $editor_configs = array(
            'name' => 'items_suffix',
            'value' => $this->getVar('items_suffix'),
            'rows' => 1,
            'cols' => 50,
            'width' => '100%',
            'height' => '200px',
            'editor' => 'Plain Text'
        );
        $suffix = new XoopsFormEditor(_AM_SYSTEM_MENUS_SUFFIXITEM, 'items_suffix', $editor_configs, false, 'textarea');
        $suffix->setDescription(_AM_SYSTEM_MENUS_SUFFIXITEM_DESC);
        $form->addElement($suffix, false);
        // url
        $form->addElement(new XoopsFormText(_AM_SYSTEM_MENUS_URLITEM, 'items_url', 50, 255, $this->getVar('items_url')), false);
        // target
        $radio = new XoopsFormRadio(_AM_SYSTEM_MENUS_TARGET, 'items_target', $this->getVar('items_target'));
        $radio->addOption(0, _AM_SYSTEM_MENUS_TARGET_SELF);
        $radio->addOption(1, _AM_SYSTEM_MENUS_TARGET_BLANK);
        $form->addElement($radio, false);
        // position
        $form->addElement(new XoopsFormText(_AM_SYSTEM_MENUS_POSITIONITEM, 'items_position', 5, 5, $position));
        // actif
        $radio = new XoopsFormRadio(_AM_SYSTEM_MENUS_ACTIVE, 'items_active', $active);
        $radio->addOption(1, _YES);
        $radio->addOption(0, _NO);
        $form->addElement($radio);

        // permission
        $permHelper = new \Xmf\Module\Helper\Permission();
        $perm = $permHelper->getGroupSelectFormForItem('menus_items_view', $this->getVar('items_id'), _AM_SYSTEM_MENUS_PERMISSION_VIEW_ITEM, 'menus_items_view_perms', true);
        $perm->setDescription(_AM_SYSTEM_MENUS_PERMISSION_VIEW_ITEM_DESC);
        $form->addElement($perm, false);

        $form->addElement(new XoopsFormHidden('op', 'saveitem'));
        // submit
        $form->addElement(new XoopsFormButton('', 'submit', _SUBMIT, 'submit'));

        return $form;
    }
}
class XoopsMenusItemsHandler extends XoopsPersistableObjectHandler
{

    /**
     * Constructor
     *
     * @param XoopsDatabase $db reference to a xoopsDB object
     */
    public function __construct($db)
    {
        // table short name, class name, key field, identifier field
        parent::__construct($db, 'menusitems', 'XoopsMenusItems', 'items_id', 'items_pid');
    }
}