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
        $form->addElement(new XoopsFormText(_AM_SYSTEM_MENUS_TITLE, 'category_title', 50, 255, $this->getVar('category_title')), true);

        // url
        $url = new XoopsFormText(_AM_SYSTEM_MENUS_URL, 'category_url', 50, 255, $this->getVar('category_url'));
        $url->setDescription(_AM_SYSTEM_MENUS_URLDESC);
        $form->addElement($url, false);

        // position
        $form->addElement(new XoopsFormText(_AM_SYSTEM_MENUS_POSITION, 'category_position', 5, 5, $position));

        // actif
        $radio = new XoopsFormRadio(_AM_SYSTEM_MENUS_ACTIVE, 'category_active', $active);
        $radio->addOption(1, _YES);
        $radio->addOption(0, _NO);
        $form->addElement($radio);

        $form->addElement(new XoopsFormHidden('op', 'save'));
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