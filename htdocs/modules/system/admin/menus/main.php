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
if ($op !== 'saveorder' && $op !== 'toggleactivecat' && $op !== 'toggleactiveitem') {
    xoops_cp_header();
    $xoopsTpl->assign('op', $op);
    $xoopsTpl->assign('xoops_token', $GLOBALS['xoopsSecurity']->getTokenHTML());

    // Define Stylesheet
    $xoTheme->addStylesheet(XOOPS_URL . '/modules/system/css/admin.css');
    $xoTheme->addStylesheet(XOOPS_URL . '/modules/system/css/menus.css');
    // Define scripts
    $xoTheme->addScript('modules/system/js/admin.js');
    $xoTheme->addScript('modules/system/js/menus.js');
    // Define Breadcrumb and tips
}




$helper = Helper::getHelper('system');
$nb_limit = $helper->getConfig('avatars_pager', 15);

switch ($op) {
    case 'list':
    default:
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
        $xoBreadCrumb->addTips(sprintf(_AM_SYSTEM_MENUS_NAV_TIPS, $GLOBALS['xoopsConfig']['language']));
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
                $category['title']           = $category_arr[$i]->getAdminTitle();
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
        /** @var \XoopsMenusCategory $obj */
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
            /** @var \XoopsMenusCategory $obj */
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
            $xoopsTpl->assign('error_message', $obj->getHtmlErrors());
        }
        break;

    case 'delcat':
        $category_id = Request::getInt('category_id', 0);
        if ($category_id == 0) {
            redirect_header('admin.php?fct=menus', 3, _AM_SYSTEM_MENUS_ERROR_NOCATEGORY);
        } else {
            $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
            $xoBreadCrumb->render();
            $surdel = Request::getBool('surdel', false);
            $menuscategoryHandler = xoops_getHandler('menuscategory');
            $menusitemsHandler = xoops_getHandler('menusitems');
            /** @var \XoopsMenusCategory $obj */
            $obj = $menuscategoryHandler->get($category_id);
            if ($surdel === true) {
                if (!$GLOBALS['xoopsSecurity']->check()) {
                    redirect_header('admin.php?fct=menus', 3, implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
                }
                if ($menuscategoryHandler->delete($obj)) {
                    // delete items in this category
                    $criteria = new CriteriaCompo();
                    $criteria->add(new Criteria('items_cid', $category_id));
                    $items_arr = $menusitemsHandler->getall($criteria);
                    foreach (array_keys($items_arr) as $i) {
                        $menusitemsHandler->delete($items_arr[$i]);
                    }
                    redirect_header('admin.php?fct=menus', 2, _AM_SYSTEM_DBUPDATED);
                } else {
                    $xoopsTpl->assign('error_message', $obj->getHtmlErrors());
                }
            } else {
                $criteria = new CriteriaCompo();
                $criteria->add(new Criteria('items_cid', $category_id));
                $items_arr = $menusitemsHandler->getall($criteria);
                $items = '<br>';
                foreach (array_keys($items_arr) as $i) {
                        $items .= '#' . $items_arr[$i]->getVar('items_id') . ': ' . $items_arr[$i]->getVar('items_title') . '<br>';
                }
                xoops_confirm([
                    'surdel'      => true,
                    'category_id' => $category_id,
                    'op'          => 'delcat'
                ], $_SERVER['REQUEST_URI'], sprintf(_AM_SYSTEM_MENUS_SUREDELCAT, $obj->getVar('category_title')) . $items);
            }
        }
        break;

    case 'delitem':
        $item_id = Request::getInt('item_id', 0);
        $category_id = Request::getInt('category_id', 0);
        if ($item_id == 0) {
            redirect_header('admin.php?fct=menus', 3, _AM_SYSTEM_MENUS_ERROR_NOITEM);
        } else {
            $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
            $xoBreadCrumb->render();
            include_once $GLOBALS['xoops']->path('class/tree.php');
            $surdel = Request::getBool('surdel', false);
            $menusitemsHandler = xoops_getHandler('menusitems');
            /** @var \XoopsMenusItems $obj */
            $obj = $menusitemsHandler->get($item_id);
            if ($obj->getVar('items_active') == 0){
                redirect_header('admin.php?fct=menus&op=viewcat&category_id=' . $category_id, 5, _AM_SYSTEM_MENUS_ERROR_ITEMDISABLE);
            }
            if ($surdel === true) {
                if (!$GLOBALS['xoopsSecurity']->check()) {
                    redirect_header('admin.php?fct=menus', 3, implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
                }
                if ($menusitemsHandler->delete($obj)) {
                    // delete subitems of this item
                    $criteria = new CriteriaCompo();
                    $criteria->add(new Criteria('items_cid', $category_id));
                    $items_arr = $menusitemsHandler->getall($criteria);
                    $myTree = new XoopsObjectTree($items_arr, 'items_id', 'items_pid');
                    $items_arr = $myTree->getAllChild($item_id);
                    foreach (array_keys($items_arr) as $i) {
                        $menusitemsHandler->delete($items_arr[$i]);
                    }
                    redirect_header('admin.php?fct=menus&op=viewcat&category_id=' . $category_id, 2, _AM_SYSTEM_DBUPDATED);
                } else {
                    $xoopsTpl->assign('error_message', $obj->getHtmlErrors());
                }
            } else {
                $criteria = new CriteriaCompo();
                $criteria->add(new Criteria('items_cid', $category_id));
                $items_arr = $menusitemsHandler->getall($criteria);
                $myTree = new XoopsObjectTree($items_arr, 'items_id', 'items_pid');
                $items_arr = $myTree->getAllChild($item_id);
                $items = '<br>';
                foreach (array_keys($items_arr) as $i) {
                        $items .= '#' . $items_arr[$i]->getVar('items_id') . ': ' . $items_arr[$i]->getVar('items_title') . '<br>';
                }
                xoops_confirm([
                    'surdel'      => true,
                    'item_id'     => $item_id,
                    'category_id' => $category_id,
                    'op'          => 'delitem'
                ], $_SERVER['REQUEST_URI'], sprintf(_AM_SYSTEM_MENUS_SUREDELITEM, $obj->getVar('items_title')) . $items);
            }
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
        $xoopsTpl->assign('category_id', $category_id);
        if ($category_id == 0) {
            $xoopsTpl->assign('error_message', _AM_SYSTEM_MENUS_ERROR_NOCATEGORY);
        } else {
            $menuscategoryHandler = xoops_getHandler('menuscategory');
            $category = $menuscategoryHandler->get($category_id);
            $xoopsTpl->assign('category_id', $category->getVar('category_id'));
            $xoopsTpl->assign('cat_title', $category->getAdminTitle());

            $menusitemsHandler = xoops_getHandler('menusitems');
            $criteria = new CriteriaCompo();
            $criteria->add(new Criteria('items_cid', $category_id));
            $criteria->setSort('items_position ASC, items_title');
            $criteria->setOrder('ASC');
            $items_arr = $menusitemsHandler->getall($criteria);
            $items_count = $menusitemsHandler->getCount($criteria);
            $xoopsTpl->assign('items_count', $items_count);
            xoops_load('SystemMenusTree', 'system');
            $myTree = new SystemMenusTree($items_arr, 'items_id', 'items_pid');
            $tree_arr = $myTree->makeTree('article_name', '--', 0);
            if ($items_count > 0) {
                foreach (array_keys($tree_arr) as $i) {
                    $items = array();
                    $items['id']       = $tree_arr[$i]['obj']->getVar('items_id');
                    $items['title']    = $tree_arr[$i]['obj']->getAdminTitle();
                    $items['url']      = $tree_arr[$i]['obj']->getVar('items_url');
                    $items['active']   = $tree_arr[$i]['obj']->getVar('items_active');
                    $items['level']    = ($tree_arr[$i]['level'] - 1);
                    $xoopsTpl->append('items', $items);
		            unset($items);
                }
            }
        }
        break;

    case 'additem':
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_CATEGORY);
        $xoBreadCrumb->render();
        $category_id = Request::getInt('category_id', 0);
        $xoopsTpl->assign('category_id', $category_id);
        // Form
        $menusitemsHandler = xoops_getHandler('menusitems');
        /** @var \XoopsMenusItems $obj */
        $obj = $menusitemsHandler->create();
        $form = $obj->getFormItems($category_id);
        $xoopsTpl->assign('form', $form->render());
        break;

    case 'saveitem':
        if (!$GLOBALS['xoopsSecurity']->check()) {
            redirect_header('admin.php?fct=menus', 3, implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
        }
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_CATEGORY);
        $xoBreadCrumb->render();

        $menusitemsHandler = xoops_getHandler('menusitems');
        $id = Request::getInt('items_id', 0);
        /** @var \XoopsMenusItems $obj */
        if ($id > 0) {
            $obj = $menusitemsHandler->get($id);
        } else {
            $obj = $menusitemsHandler->create();
        }
        $error_message = '';
        $itempid = Request::getInt('items_pid', 0);
        if ($itempid == $id && $itempid != 0) {
            $error_message .= _AM_SYSTEM_MENUS_ERROR_ITEMPARENT;
        } else {
            $obj->setVar('items_pid', $itempid);
        }
        $items_cid = Request::getInt('items_cid', 0);
        $obj->setVar('items_cid', $items_cid);
        $obj->setVar('items_title', Request::getString('items_title', ''));
        $obj->setVar('items_url', Request::getString('items_url', ''));
        $obj->setVar('items_position', Request::getInt('items_position', 0));
        $obj->setVar('items_active', Request::getInt('items_active', 1));
        if ($error_message == '') {
            if ($menusitemsHandler->insert($obj)) {
                redirect_header('admin.php?fct=menus&op=viewcat&category_id=' . $items_cid, 2, _AM_SYSTEM_DBUPDATED);
            } else {
                $xoopsTpl->assign('error_message', $obj->getHtmlErrors());
            }
        } else {
            /** @var \XoopsMenusItems $obj */
            $form = $obj->getFormItems($items_cid);
            $xoopsTpl->assign('form', $form->render());
            $xoopsTpl->assign('error_message', $error_message);
        }
        break;

    case 'edititem':
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_MAIN, system_adminVersion('menus', 'adminpath'));
        $xoBreadCrumb->addLink(_AM_SYSTEM_MENUS_NAV_CATEGORY);
        $xoBreadCrumb->render();
        // Form
        $item_id = Request::getInt('item_id', 0);
        $category_id = Request::getInt('category_id', 0);
        $xoopsTpl->assign('category_id', $category_id);
        if ($item_id == 0 || $category_id == 0) {
            if ($item_id == 0) {
                $xoopsTpl->assign('error_message', _AM_SYSTEM_MENUS_ERROR_NOITEM);
            }
            if ($category_id == 0) {
                $xoopsTpl->assign('error_message', _AM_SYSTEM_MENUS_ERROR_NOCATEGORY);
            }
        } else {
            $menusitemsHandler = xoops_getHandler('menusitems');
            /** @var \XoopsMenusItems $obj */
            $obj = $menusitemsHandler->get($item_id);
            if ($obj->getVar('items_active') == 0){
                redirect_header('admin.php?fct=menus&op=viewcat&category_id=' . $category_id, 5, _AM_SYSTEM_MENUS_ERROR_ITEMEDIT);
            }
            $form = $obj->getFormItems($category_id);
            $xoopsTpl->assign('form', $form->render());
        }
        break;

    case 'toggleactivecat':
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

        // cascade to all items of this category (and their children)
        $updatedItems = [];
        if ($res) {
            $menusitemsHandler = xoops_getHandler('menusitems');
            if (!is_object($menusitemsHandler) && class_exists('XoopsMenusItemsHandler')) {
                $menusitemsHandler = new XoopsMenusItemsHandler($GLOBALS['xoopsDB']);
            }
            /**
             * Recursively update items under a parent (same as toggleactiveitem)
             */
            $recursiveUpdate = function ($handler, $parentId, $state, array &$updated) use (&$recursiveUpdate) {
                $crit = new Criteria('items_pid', (int)$parentId);
                $children = $handler->getAll($crit);
                foreach ($children as $child) {
                    $cid = $child->getVar('items_id');
                    if ((int)$child->getVar('items_active') !== $state) {
                        $child->setVar('items_active', $state);
                        if ($handler->insert($child, true)) {
                            $updated[] = $cid;
                        }
                    }
                    $recursiveUpdate($handler, $cid, $state, $updated);
                }
            };
            // first update all direct items of category
            $critCat = new Criteria('items_cid', $category_id);
            $allItems = $menusitemsHandler->getAll($critCat);
            foreach ($allItems as $itm) {
                $idtmp = $itm->getVar('items_id');
                if ((int)$itm->getVar('items_active') !== $new) {
                    $itm->setVar('items_active', $new);
                    if ($menusitemsHandler->insert($itm, true)) {
                        $updatedItems[] = $idtmp;
                    }
                }
                // propagate to children of this item
                $recursiveUpdate($menusitemsHandler, $idtmp, $new, $updatedItems);
            }
        }

        header('Content-Type: application/json');
        if ($res) {
            $response = ['success' => true, 'active' => (int)$new, 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()];
            if (!empty($updatedItems)) {
                $response['updated'] = array_values($updatedItems);
            }
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'Save failed', 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
        }
        exit;
        break;

    case 'toggleactiveitem':
        // Disable logger & clear buffers for clean JSON response
        if (isset($GLOBALS['xoopsLogger']) && is_object($GLOBALS['xoopsLogger'])) {
            $GLOBALS['xoopsLogger']->activated = false;
        }
        while (ob_get_level()) {
            @ob_end_clean();
        }

        // token check
        if (!$GLOBALS['xoopsSecurity']->check()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => implode(' ', $GLOBALS['xoopsSecurity']->getErrors()),
                'token'   => $GLOBALS['xoopsSecurity']->getTokenHTML()
            ]);
            exit;
        }

        $item_id = Request::getInt('item_id', 0);
        if ($item_id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid id', 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
            exit;
        }

        $menusitemsHandler = xoops_getHandler('menusitems');
        if (!is_object($menusitemsHandler) && class_exists('XoopsMenusItemsHandler')) {
            $menusitemsHandler = new XoopsMenusItemsHandler($GLOBALS['xoopsDB']);
        }

        $obj = $menusitemsHandler->get($item_id);
        if (!is_object($obj)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Not found', 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
            exit;
        }

        // Adapt field name si nécessaire (ici 'items_active')
        $current = (int)$obj->getVar('items_active');
        $new = $current ? 0 : 1;
        // if we try to activate, ensure the parent/ancestors are active
        if ($new) {
            $parentId = (int)$obj->getVar('items_pid');
            while ($parentId > 0) {
                $parentObj = $menusitemsHandler->get($parentId);
                if (!is_object($parentObj)) {
                    break;
                }
                if ((int)$parentObj->getVar('items_active') === 0) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => _AM_SYSTEM_MENUS_ERROR_PARENTINACTIVE,
                        'token'   => $GLOBALS['xoopsSecurity']->getTokenHTML()
                    ]);
                    exit;
                }
                $parentId = (int)$parentObj->getVar('items_pid');
            }
        }
        $obj->setVar('items_active', $new);
        $res = $menusitemsHandler->insert($obj, true);

        // propagate new state to all children recursively
        $updatedChildren = [];
        if ($res) {
            /**
             * Recursively update child items to match parent state
             *
             * @param \XoopsPersistableObjectHandler $handler
             * @param int                            $parentId
             * @param int                            $state
             * @param array                          $updated   (passed by reference)
             */
            function propagateActiveState($handler, $parentId, $state, array &$updated)
            {
                $criteria = new Criteria('items_pid', (int)$parentId);
                /** @var \XoopsObject[] $children */
                $children = $handler->getAll($criteria);
                foreach ($children as $child) {
                    $childId = $child->getVar('items_id');
                    if ((int)$child->getVar('items_active') !== $state) {
                        $child->setVar('items_active', $state);
                        if ($handler->insert($child, true)) {
                            $updated[] = $childId;
                        }
                    }
                    propagateActiveState($handler, $childId, $state, $updated);
                }
            }

            propagateActiveState($menusitemsHandler, $item_id, $new, $updatedChildren);
        }

        header('Content-Type: application/json');
        if ($res) {
            $response = ['success' => true, 'active' => (int)$new, 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()];
            if (!empty($updatedChildren)) {
                $response['updated'] = array_values($updatedChildren);
            }
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'Save failed', 'token' => $GLOBALS['xoopsSecurity']->getTokenHTML()]);
        }
        exit;
        break;

}
if ($op !== 'saveorder' && $op !== 'toggleactivecat' && $op !== 'toggleactiveitem') {
    // Call Footer
    xoops_cp_footer();
}