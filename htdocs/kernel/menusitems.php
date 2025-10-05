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
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->initVar('items_id', XOBJ_DTYPE_INT, null, false);
        $this->initVar('items_pid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('items_cid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('items_title', XOBJ_DTYPE_TXTBOX, null);
        $this->initVar('items_url', XOBJ_DTYPE_TXTBOX, null);
        $this->initVar('items_position', XOBJ_DTYPE_INT, null, false);
        $this->initVar('items_active', XOBJ_DTYPE_INT, 1);
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
        include_once $GLOBALS['xoops']->path('class/tree.php');
        $myTree = new XoopsObjectTree($item_arr, 'items_id', 'items_pid');
        $suparticle = $myTree->makeSelectElement('items_pid', 'items_title', '--', $this->getVar('items_pid'), true, 0, '', _AM_SYSTEM_MENUS_PID);
        $form->addElement($suparticle, false);

        // title
        $form->addElement(new XoopsFormText(_AM_SYSTEM_MENUS_TITLEITEM, 'items_title', 50, 255, $this->getVar('category_title')), true);

        // url
        $form->addElement(new XoopsFormText(_AM_SYSTEM_MENUS_URLITEM, 'items_url', 50, 255, $this->getVar('category_url')), false);

        // position
        $form->addElement(new XoopsFormText(_AM_SYSTEM_MENUS_POSITIONITEM, 'items_position', 5, 5, $position));

        // actif
        $radio = new XoopsFormRadio(_AM_SYSTEM_MENUS_ACTIVE, 'items_active', $active);
        $radio->addOption(1, _YES);
        $radio->addOption(0, _NO);
        $form->addElement($radio);

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