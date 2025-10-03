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
 * @package      system
 * @subpackage   menus
 * @since        2.7.0
 * @author       XOOPS Development Team, Grégory Mage (AKA GregMage)
 */

use Xmf\Request;
use Xmf\Module\Helper;

// Check users rights
if (!is_object($xoopsUser) || !is_object($xoopsModule) || !$xoopsUser->isAdmin($xoopsModule->mid())) {
    exit(_NOPERM);
}

// Define main template
$GLOBALS['xoopsOption']['template_main'] = 'system_menus.tpl';

// Get Action type
$op = Request::getCmd('op', 'list');

// Call Header
if ($op !== 'saveorder' && $op !== 'toggleactive') {
    xoops_cp_header();
    $xoopsTpl->assign('op', $op);
    $xoopsTpl->assign('xoops_token', $GLOBALS['xoopsSecurity']->getTokenHTML());

    // Define Stylesheet
    $xoTheme->addStylesheet(XOOPS_URL . '/modules/system/css/admin.css');
    // Define scripts
    $xoTheme->addScript('modules/system/js/admin.js');
    // Define Breadcrumb and tips
}




$helper = Helper::getHelper('system');
$nb_limit = $helper->getConfig('avatars_pager', 15);

switch ($op) {
    case 'list':
    default:
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
        $xoBreadCrumb->render();
        $start = Request::getInt('start', 0);
        /** @var \XoopsPersistableObjectHandler $menuscategoryHandler */
        $menuscategoryHandler = xoops_getHandler('menuscategory');
        $criteria = new CriteriaCompo();
        $criteria->setSort('category_position');
        $criteria->setOrder('ASC');
        $criteria->setStart($start);
        $criteria->setLimit($nb_limit);
        $category_arr = $menuscategoryHandler->getall($criteria);
        $category_count = $menuscategoryHandler->getCount($criteria);
        $xoopsTpl->assign('category_count', $category_count);
        if ($category_count > 0) {
            foreach (array_keys($category_arr) as $i) {
                $category = array();
                $category['id']              = $category_arr[$i]->getVar('category_id');
                $category['title']           = $category_arr[$i]->getVar('category_title');
                $category['url']             = $category_arr[$i]->getVar('category_url');
                $category['position']        = $category_arr[$i]->getVar('category_position');
                $category['active']          = $category_arr[$i]->getVar('category_active');
                $category_img                = $category_arr[$i]->getVar('category_logo');
                $xoopsTpl->append('category', $category);
                unset($category);
            }
            // Display Page Navigation
            if ($category_count > $nb_limit) {
                $nav = new XoopsPageNav($category_count, $nb_limit, $start, 'start');
                $xoopsTpl->assign('nav_menu', $nav->renderNav(4));
            }
        } else {
            $xoopsTpl->assign('error_message', _AM_SYSTEM_MENUS_ERROR_NOCATEGORY);
        }
        break;

    case 'addcat':
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
        $xoBreadCrumb->render();
        // Form
        $menuscategoryHandler = xoops_getHandler('menuscategory');
        $obj                  = $menuscategoryHandler->create();
        $form = $obj->getFormCat();
        $xoopsTpl->assign('form', $form->render());
        break;

    case 'editcat':
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
        $xoBreadCrumb->render();
        // Form
        $category_id = Request::getInt('category_id', 0);
        if ($category_id == 0) {
            $xoopsTpl->assign('error_message', _AM_SYSTEM_MENUS_ERROR_NOCATEGORY);
        } else {
            $menuscategoryHandler = xoops_getHandler('menuscategory');
            $obj = $menuscategoryHandler->get($category_id);
            $form = $obj->getFormCat();
            $xoopsTpl->assign('form', $form->render());
        }
        break;

    case 'savecat':
        if (!$GLOBALS['xoopsSecurity']->check()) {
            redirect_header('admin.php?fct=menus', 3, implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
        }
        $menuscategoryHandler = xoops_getHandler('menuscategory');
        $id = Request::getInt('category_id', 0);
        if ($id > 0) {
            $obj = $menuscategoryHandler->get($id);
        } else {
            $obj = $menuscategoryHandler->create();
        }
        $obj->setVar('category_title', Request::getString('category_title', ''));
        $obj->setVar('category_url', Request::getString('category_url', ''));
        $obj->setVar('category_position', Request::getInt('category_position', 0));
        $obj->setVar('category_active', Request::getInt('category_active', 1));
        if ($menuscategoryHandler->insert($obj)) {
            redirect_header('admin.php?fct=menus', 2, _AM_SYSTEM_DBUPDATED);
        } else {
            echo $obj->getHtmlErrors();
        }
        break;

    case 'saveorder':
        // Pour les réponses AJAX : désactiver le logger et vider les buffers
        if (isset($GLOBALS['xoopsLogger']) && is_object($GLOBALS['xoopsLogger'])) {
            $GLOBALS['xoopsLogger']->activated = false;
        }
        while (ob_get_level()) {
            @ob_end_clean();
        }
        // vérifie le token
        if (!$GLOBALS['xoopsSecurity']->check()) {
            // debug: renvoyer les erreurs et ce qui a été reçu (retirer en production)
            header('Content-Type: application/json');
            $errors = $GLOBALS['xoopsSecurity']->getErrors();
            echo json_encode([
                'success' => false,
                'message' => implode(' ', $errors),
                'token'   => $GLOBALS['xoopsSecurity']->getTokenHTML()
            ]);
            exit;
        }

        $order = Request::getArray('order', []);
        if (!is_array($order) || count($order) === 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No order provided', 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
            exit;
        }

        $menuscategoryHandler = xoops_getHandler('menuscategory');
        if (!is_object($menuscategoryHandler) && class_exists('XoopsMenusCategoryHandler')) {
            $menuscategoryHandler = new XoopsMenusCategoryHandler($GLOBALS['xoopsDB']);
        }

        $pos = 1;
        $errors = [];
        foreach ($order as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $obj = $menuscategoryHandler->get($id);
            if (is_object($obj)) {
                $obj->setVar('category_position', $pos);
                if (!$menuscategoryHandler->insert($obj, true)) {
                    $errors[] = "Failed to update id {$id}";
                }
            } else {
                $errors[] = "Not found id {$id}";
            }
            $pos++;
        }

        header('Content-Type: application/json');
        if (empty($errors)) {
            echo json_encode(['success' => true, 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
        } else {
            echo json_encode(['success' => false, 'message' => implode('; ', $errors), 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
        }
        exit;
        break;

    case 'viewcat':
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_CATEGORY);
        $xoBreadCrumb->render();
        $category_id = Request::getInt('category_id', 0);
        if ($category_id == 0) {
            $xoopsTpl->assign('error_message', _AM_SYSTEM_MENUS_ERROR_NOCATEGORY);
        } else {
            $menuscategoryHandler = xoops_getHandler('menuscategory');
            $category = $menuscategoryHandler->get($category_id);
            $xoopsTpl->assign('cat_id', $category->getVar('category_id'));
            $xoopsTpl->assign('cat_title', $category->getVar('category_title'));
        }
        break;

    case 'additem':
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_CATEGORY);
        $xoBreadCrumb->render();
        $category_id = Request::getInt('category_id', 0);
        $xoopsTpl->assign('cat_id', $category_id);
        // Form
        $menusitemsHandler = xoops_getHandler('menusitems');
        $obj = $menusitemsHandler->create();
        $form = $obj->getFormItems($category_id);
        $xoopsTpl->assign('form', $form->render());
        break;

    case 'saveitem':
        if (!$GLOBALS['xoopsSecurity']->check()) {
            redirect_header('admin.php?fct=menus', 3, implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
        }
        $menusitemsHandler = xoops_getHandler('menusitems');
        $id = Request::getInt('items_id', 0);
        if ($id > 0) {
            $obj = $menusitemsHandler->get($id);
        } else {
            $obj = $menusitemsHandler->create();
        }
        $obj->setVar('items_pid', Request::getInt('items_pid', 0));
        $items_cid = Request::getInt('items_cid', 0);
        $obj->setVar('items_cid', $items_cid);
        $obj->setVar('items_title', Request::getString('items_title', ''));
        $obj->setVar('items_url', Request::getString('items_url', ''));
        $obj->setVar('items_position', Request::getInt('items_position', 0));
        $obj->setVar('items_active', Request::getInt('items_active', 1));
        if ($menusitemsHandler->insert($obj)) {
            redirect_header('admin.php?fct=menus&op=viewcat&category_id=' . $items_cid, 2, _AM_SYSTEM_DBUPDATED);
        } else {
            echo $obj->getHtmlErrors();
        }
        break;

    case 'toggleactive':
        // Pour les réponses AJAX : désactiver le logger et vider les buffers
        if (isset($GLOBALS['xoopsLogger']) && is_object($GLOBALS['xoopsLogger'])) {
            $GLOBALS['xoopsLogger']->activated = false;
        }
        while (ob_get_level()) {
            @ob_end_clean();
        }
        // Vérifier token
        if (!$GLOBALS['xoopsSecurity']->check()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => implode(' ', $GLOBALS['xoopsSecurity']->getErrors()),
                'token'   => $GLOBALS['xoopsSecurity']->getTokenHTML()
            ]);
            exit;
        }

        $category_id = Request::getInt('category_id', 0);
        if ($category_id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid id', 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
            exit;
        }

        $menuscategoryHandler = xoops_getHandler('menuscategory');
        if (!is_object($menuscategoryHandler) && class_exists('XoopsMenusCategoryHandler')) {
            $menuscategoryHandler = new XoopsMenusCategoryHandler($GLOBALS['xoopsDB']);
        }

        $obj = $menuscategoryHandler->get($category_id);
        if (!is_object($obj)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Not found', 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
            exit;
        }
        $new = $obj->getVar('category_active') ? 0 : 1;
        $obj->setVar('category_active', $new);
        $res = $menuscategoryHandler->insert($obj, true);

        header('Content-Type: application/json');
        if ($res) {
            echo json_encode(['success' => true, 'active' => (int)$new, 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Save failed', 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
        }
        exit;
        break;

}
if ($op !== 'saveorder' && $op !== 'toggleactive') {
    // Call Footer
    xoops_cp_footer();
}