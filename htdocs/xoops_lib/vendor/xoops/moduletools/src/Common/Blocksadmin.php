<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.
*/

/**
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author    XOOPS Development Team
 */

use Xmf\Request;
use Xmf\Module\Helper;
use Xoops\Helpers\Service\Url;
use Xoops\Helpers\Utility\HtmlBuilder;

/**
 * class Blocksadmin
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
class Blocksadmin
{
    /**
     * @var \XoopsMySQLDatabase|null
     */
    public $db;
    /**
     * @var string
     */
    public string $moduleDirName;
    /**
     * @var string
     */
    public $moduleDirNameUpper;

    /**
     * Blocksadmin constructor.
     * @param \XoopsDatabase|null $db
     * @param Helper|null         $helper
     */
    public function __construct(?\XoopsDatabase $db, public Helper $helper)
    {
        $db ??= \XoopsDatabaseFactory::getDatabaseConnection();
        $this->db                 = $db;
        $module = $this->helper->getModule();
        $this->moduleDirName      = $module instanceof \XoopsModule
            ? (string)$module->getVar('dirname')
            : $this->helper->dirname();
        $this->moduleDirNameUpper = \mb_strtoupper($this->moduleDirName);

        \xoops_loadLanguage('admin', 'system');
        \xoops_loadLanguage('admin/blocksadmin', 'system');
        \xoops_loadLanguage('admin/groups', 'system');
        \xoops_loadLanguage('common', $this->moduleDirName);
    }

    /**
     * @return void
     */
    public function listBlocks(): void
    {
        global $xoopsModule, $pathIcon16;
        require_once XOOPS_ROOT_PATH . '/class/xoopslists.php';


        /** @var \XoopsModuleHandler $moduleHandler */
        $moduleHandler = xoops_getHandler('module');
        /** @var \XoopsMemberHandler $memberHandler */
        $memberHandler = xoops_getHandler('member');
        /** @var \XoopsGroupPermHandler $grouppermHandler */
        $grouppermHandler = xoops_getHandler('groupperm');
        $groups           = $memberHandler->getGroups();
        $criteria         = new \CriteriaCompo(new \Criteria('hasmain', '1'));
        $criteria->add(new \Criteria('isactive', '1'));
        $moduleList     = $moduleHandler->getList($criteria);
        $moduleList[-1] = \_AM_SYSTEM_BLOCKS_TOPPAGE;
        $moduleList[0]  = \_AM_SYSTEM_BLOCKS_ALLPAGES;
        \ksort($moduleList);
        echo "<h4 style='text-align:left;'>" . \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'BADMIN') . '</h4>';
        $scriptName = Request::getString('SCRIPT_NAME', '', 'SERVER');
        echo "<form action='" . HtmlBuilder::escape($scriptName) . "' name='blockadmin' method='post'>";
        echo "<table width='100%' class='outer' cellpadding='4' cellspacing='1'>
        <tr valign='middle'><th align='center'>" . \_AM_SYSTEM_BLOCKS_TITLE . "</th><th align='center' nowrap='nowrap'>" . \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'SIDE') . '<br>' . \_LEFT . '-' . \_CENTER . '-' . \_RIGHT . "</th>
        <th align='center'>" . \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'WEIGHT') . "</th>
        <th align='center'>" . \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'VISIBLE') . "</th><th align='center'>" . \_AM_SYSTEM_BLOCKS_VISIBLEIN . "</th>
        <th align='center'>" . \_AM_SYSTEM_ADGS . "</th>
        <th align='center'>" . \_AM_SYSTEM_BLOCKS_BCACHETIME . "</th>
        <th align='center'>" . \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'ACTION') . '</th>
        </tr>';
        $blockArray = \XoopsBlock::getByModule($xoopsModule->mid());
        $blockCount = \count($blockArray);
        // One-time token for the delete links; deleteBlock() validates it (see assertToken()).
        $security    = self::runtimeGlobal('xoopsSecurity');
        $deleteToken = \is_object($security) && \method_exists($security, 'createToken') ? (string) $security->createToken() : '';
        $class      = 'even';
        $cachetimes = [
            0       => \_NOCACHE,
            30      => \sprintf(\_SECONDS, 30),
            60      => \_MINUTE,
            300     => \sprintf(\_MINUTES, 5),
            1800    => \sprintf(\_MINUTES, 30),
            3600    => \_HOUR,
            18000   => \sprintf(\_HOURS, 5),
            86400   => \_DAY,
            259200  => \sprintf(\_DAYS, 3),
            604800  => \_WEEK,
            2592000 => \_MONTH,
        ];
        foreach ($blockArray as $i) {
            $groupsPermissions = $grouppermHandler->getGroupIds('block_read', $i->getVar('bid'));
            $sql               = 'SELECT module_id FROM ' . $this->db->prefix('block_module_link') . ' WHERE block_id=' . $i->getVar('bid');
            $result            = $this->db->query($sql);
            if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
                \trigger_error("Query Failed! SQL: $sql Error: " . $this->db->error(), \E_USER_ERROR);
            }
            $modules           = [];
            while (false !== ($row = (($this->db->isResultSet($result) && ($result instanceof \mysqli_result)) ? $this->db->fetchArray($result) : false))) {
                $modules[] = (int)$row['module_id'];
            }

            $cachetimeOptions = '';
            foreach ($cachetimes as $cachetime => $cachetimeName) {
                if ($i->getVar('bcachetime') == $cachetime) {
                    $cachetimeOptions .= "<option value='$cachetime' selected='selected'>$cachetimeName</option>\n";
                } else {
                    $cachetimeOptions .= "<option value='$cachetime'>$cachetimeName</option>\n";
                }
            }

            $ssel7 = '';
            $ssel6 = $ssel7;
            $ssel5 = $ssel6;
            $ssel4 = $ssel5;
            $ssel3 = $ssel4;
            $ssel2 = $ssel3;
            $ssel1 = $ssel2;
            $ssel0 = $ssel1;
            $sel1  = $ssel0;
            $sel0  = $sel1;
            if (1 === $i->getVar('visible')) {
                $sel1 = ' checked';
            } else {
                $sel0 = ' checked';
            }
            if (\XOOPS_SIDEBLOCK_LEFT === $i->getVar('side')) {
                $ssel0 = ' checked';
            } elseif (\XOOPS_SIDEBLOCK_RIGHT === $i->getVar('side')) {
                $ssel1 = ' checked';
            } elseif (\XOOPS_CENTERBLOCK_LEFT === $i->getVar('side')) {
                $ssel2 = ' checked';
            } elseif (\XOOPS_CENTERBLOCK_RIGHT === $i->getVar('side')) {
                $ssel4 = ' checked';
            } elseif (\XOOPS_CENTERBLOCK_CENTER === $i->getVar('side')) {
                $ssel3 = ' checked';
            } elseif (\XOOPS_CENTERBLOCK_BOTTOMLEFT === $i->getVar('side')) {
                $ssel5 = ' checked';
            } elseif (\XOOPS_CENTERBLOCK_BOTTOMRIGHT === $i->getVar('side')) {
                $ssel6 = ' checked';
            } elseif (\XOOPS_CENTERBLOCK_BOTTOM === $i->getVar('side')) {
                $ssel7 = ' checked';
            }
            if ('' === $i->getVar('title')) {
                $title = '&nbsp;';
            } else {
                // A stored _MI_* token resolves through the module's modinfo for display;
                // the hidden oldtitle below keeps the raw value so orderBlock() can tell
                // an unchanged resolved label from a real edit.
                $title = \method_exists($i, 'title') ? (string) $i->title('s') : $i->getVar('title');
            }
            echo "<tr valign='top'><td class='$class' align='center'><input type='text' name='title[" . $i->getVar('bid') . "]' value='" . $title . "'></td>
            <td class='$class' align='center' nowrap='nowrap'><div align='center' >
                    <input type='radio' name='side[" . $i->getVar('bid') . "]' value='" . \XOOPS_CENTERBLOCK_LEFT . "'$ssel2>
                    <input type='radio' name='side[" . $i->getVar('bid') . "]' value='" . \XOOPS_CENTERBLOCK_CENTER . "'$ssel3>
                    <input type='radio' name='side[" . $i->getVar('bid') . "]' value='" . \XOOPS_CENTERBLOCK_RIGHT . "'$ssel4>
                    </div>
                    <div>
                        <span style='float:right;'><input type='radio' name='side[" . $i->getVar('bid') . "]' value='" . \XOOPS_SIDEBLOCK_RIGHT . "'$ssel1></span>
                    <div align='left'><input type='radio' name='side[" . $i->getVar('bid') . "]' value='" . \XOOPS_SIDEBLOCK_LEFT . "'$ssel0></div>
                    </div>
                    <div align='center'>
                    <input type='radio' name='side[" . $i->getVar('bid') . "]' value='" . \XOOPS_CENTERBLOCK_BOTTOMLEFT . "'$ssel5>
                        <input type='radio' name='side[" . $i->getVar('bid') . "]' value='" . \XOOPS_CENTERBLOCK_BOTTOM . "'$ssel7>
                    <input type='radio' name='side[" . $i->getVar('bid') . "]' value='" . \XOOPS_CENTERBLOCK_BOTTOMRIGHT . "'$ssel6>
                    </div>
                </td>
                <td class='$class' align='center'><input type='text' name='weight[" . $i->getVar('bid') . "]' value='" . $i->getVar('weight') . "' size='5' maxlength='5'></td>
                <td class='$class' align='center' nowrap><input type='radio' name='visible[" . $i->getVar('bid') . "]' value='1'$sel1>" . \_YES . "&nbsp;<input type='radio' name='visible[" . $i->getVar('bid') . "]' value='0'$sel0>" . \_NO . '</td>';

            echo "<td class='$class' align='center'><select size='5' name='bmodule[" . $i->getVar('bid') . "][]' id='bmodule[" . $i->getVar('bid') . "][]' multiple='multiple'>";
            foreach ($moduleList as $k => $v) {
                echo "<option value='$k'" . (\in_array($k, $modules) ? " selected='selected'" : '') . ">$v</option>";
            }
            echo '</select></td>';

            echo "<td class='$class' align='center'><select size='5' name='groups[" . $i->getVar('bid') . "][]' id='groups[" . $i->getVar('bid') . "][]' multiple='multiple'>";
            foreach ($groups as $grp) {
                echo "<option value='" . $grp->getVar('groupid') . "' " . (\in_array($grp->getVar('groupid'), $groupsPermissions) ? " selected='selected'" : '') . '>' . $grp->getVar('name') . '</option>';
            }
            echo '</select></td>';

            // Cache lifetime
            echo '<td class="' . $class . '" align="center"> <select name="bcachetime[' . $i->getVar('bid') . ']" size="1">' . $cachetimeOptions . '</select>
                                    </td>';

            // Actions

            echo "<td class='$class' align='center'>
                <a href='blocksadmin.php?op=edit&amp;bid=" . $i->getVar('bid') . "'><img src=" . $pathIcon16 . '/edit.png' . " alt='" . \_EDIT . "' title='" . \_EDIT . "'></a>
                <a href='blocksadmin.php?op=clone&amp;bid=" . $i->getVar('bid') . "'><img src=" . $pathIcon16 . '/editcopy.png' . " alt='" . \_CLONE . "' title='" . \_CLONE . "'></a>";
            //            if ('S' !== $i->getVar('block_type') && 'M' !== $i->getVar('block_type')) {
            //                echo "&nbsp;<a href='" . XOOPS_URL . '/modules/system/admin.php?fct=blocksadmin&amp;op=delete&amp;bid=' . $i->getVar('bid') . "'><img src=" . $pathIcon16 . '/delete.png' . " alt='" . _DELETE . "' title='" . _DELETE . "'>
            //                     </a>";
            //            }

            //            if ('S' !== $i->getVar('block_type') && 'M' !== $i->getVar('block_type')) {
            if (!\in_array($i->getVar('block_type'), ['M', 'S'])) {
                echo "&nbsp;
                <a href='blocksadmin.php?op=delete&amp;bid=" . $i->getVar('bid') . '&amp;XOOPS_TOKEN_REQUEST=' . $deleteToken . "'><img src=" . $pathIcon16 . '/delete.png' . " alt='" . \_DELETE . "' title='" . \_DELETE . "'>
                     </a>";
            }
            echo "
            <input type='hidden' name='oldtitle[" . $i->getVar('bid') . "]' value='" . HtmlBuilder::escape((string) $i->getVar('title', 'n')) . "'>
            <input type='hidden' name='oldside[" . $i->getVar('bid') . "]' value='" . $i->getVar('side') . "'>
            <input type='hidden' name='oldweight[" . $i->getVar('bid') . "]' value='" . $i->getVar('weight') . "'>
            <input type='hidden' name='oldvisible[" . $i->getVar('bid') . "]' value='" . $i->getVar('visible') . "'>
            <input type='hidden' name='oldgroups[" . $i->getVar('bid') . "]' value='" . \implode(',', \array_map(\intval(...), $groupsPermissions)) . "'>
            <input type='hidden' name='oldbcachetime[" . $i->getVar('bid') . "]' value='" . $i->getVar('bcachetime') . "'>
            <input type='hidden' name='bid[" . $i->getVar('bid') . "]' value='" . $i->getVar('bid') . "'>
            </td></tr>
            ";
            $class = ('even' === $class) ? 'odd' : 'even';
        }
        echo "<tr><td class='foot' align='center' colspan='8'>
        <input type='hidden' name='op' value='order'>" . (\is_object($security) && \method_exists($security, 'getTokenHTML') ? $security->getTokenHTML() : '') . "
        <input type='submit' name='submit' value='" . \_SUBMIT . "'>
        </td></tr>
        </table>
        </form>
        <br><br>";
    }

    /**
     * @param int $bid
     */
    public function deleteBlock(int $bid): void
    {
        $this->assertToken();

        $myblock = new \XoopsBlock($bid);
        // Module ('M') and system ('S') blocks are declared in xoops_version.php; the list hides
        // their delete link and the server refuses the request as well.
        if (\in_array((string) $myblock->getVar('block_type', 'n'), ['M', 'S'], true)) {
            $this->helper->redirect('admin/blocksadmin.php?op=list', 3, \defined('_NOPERM') ? \_NOPERM : 'Permission denied.');
            return;
        }

        $sql    = \sprintf('DELETE FROM `%s` WHERE bid = %u', $this->db->prefix('newblocks'), $bid);
        $result = $this->db->exec($sql);
        if (!$result) {
            \trigger_error("Query Failed! SQL: $sql Error: " . $this->db->error(), \E_USER_ERROR);
        }
        $sql = \sprintf('DELETE FROM `%s` WHERE block_id = %u', $this->db->prefix('block_module_link'), $bid);
        $result = $this->db->exec($sql);
        if (!$result) {
            \trigger_error("Query Failed! SQL: $sql Error: " . $this->db->error(), \E_USER_ERROR);
        }
        // The block's own permission and template rows go with it; nothing else references them.
        $sql = \sprintf("DELETE FROM `%s` WHERE gperm_itemid = %u AND gperm_modid = 1 AND gperm_name = 'block_read'", $this->db->prefix('group_permission'), $bid);
        $this->db->exec($sql);
        $sql = \sprintf("DELETE FROM `%s` WHERE tpl_refid = %u AND tpl_type = 'block'", $this->db->prefix('tplfile'), $bid);
        $this->db->exec($sql);

        $this->helper->redirect('admin/blocksadmin.php?op=list', 1, _AM_DBUPDATED);
    }

    /**
     * @param int $bid
     */
    public function cloneBlock(int $bid): void
    {
        //require __DIR__ . '/admin_header.php';
        //        \xoops_cp_header();



        $myblock = new \XoopsBlock($bid);
        $sql     = 'SELECT module_id FROM ' . $this->db->prefix('block_module_link') . ' WHERE block_id=' . $bid;
        $result  = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            \trigger_error("Query Failed! SQL: $sql Error: " . $this->db->error(), \E_USER_ERROR);
        }
        $modules = [];
        while (false !== ($row = (($this->db->isResultSet($result) && ($result instanceof \mysqli_result)) ? $this->db->fetchArray($result) : false))) {
            $modules[] = (int)$row['module_id'];
        }

        $isCustom = \in_array($myblock->getVar('block_type'), ['C', 'E']);
        $block    = [
            'title'      => $myblock->getVar('title') . ' Clone',
            'form_title' => \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'BLOCKS_CLONEBLOCK'),
            'name'       => $myblock->getVar('name'),
            'side'       => $myblock->getVar('side'),
            'weight'     => $myblock->getVar('weight'),
            'visible'    => $myblock->getVar('visible'),
            'content'    => $myblock->getVar('content', 'N'),
            'modules'    => $modules,
            'is_custom'  => $isCustom,
            'ctype'      => $myblock->getVar('c_type'),
            'bcachetime' => $myblock->getVar('bcachetime'),
            'op'         => 'clone_ok',
            'bid'        => $myblock->getVar('bid'),
            'edit_form'  => $myblock->getOptions(),
            'template'   => $myblock->getVar('template'),
            'options'    => $myblock->getVar('options'),
        ];
        echo '<a href="blocksadmin.php">' . \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'BADMIN') . '</a>&nbsp;<span style="font-weight:bold;">&raquo;&raquo;</span>&nbsp;' . \_AM_SYSTEM_BLOCKS_CLONEBLOCK . '<br><br>';
        //        $form = new Blockform();
        //        $form->render();

        $this->render($block);
        //        xoops_cp_footer();
        //        require_once __DIR__ . '/admin_footer.php';
        //        exit();
    }

    /**
     * @param int        $bid
     * @param string     $bside
     * @param string     $bweight
     * @param string     $bvisible
     * @param string     $bcachetime
     * @param array|null $bmodule
     * @param array|null $options
     * @param array|null $groups
     */
    public function isBlockCloned(int $bid, string $bside, string $bweight, string $bvisible, string $bcachetime, ?array $bmodule, ?array $options, ?array $groups): void
    {
        $this->assertToken();

        $block = new \XoopsBlock($bid);
        /** @var \XoopsBlock $clone */
        $clone = $block->xoopsClone();
        if (empty($bmodule)) {
            //            \xoops_cp_header();
            \xoops_error(\sprintf(\_AM_NOTSELNG, _AM_VISIBLEIN));
            \xoops_cp_footer();
            exit();
        }
        $clone->setVar('side', $bside);
        $clone->setVar('weight', $bweight);
        $clone->setVar('visible', $bvisible);
        //$clone->setVar('content', $_POST['bcontent']);
        $clone->setVar('title', Request::getString('btitle', '', 'POST'));
        $clone->setVar('bcachetime', $bcachetime);
        if (\is_array($options) && (\count($options) > 0)) {
            $options = \implode('|', $options);
            $clone->setVar('options', $options);
        }
        $clone->setVar('bid', 0);
        if (\in_array($block->getVar('block_type'), ['C', 'E'])) {
            $clone->setVar('block_type', 'E');
        } else {
            $clone->setVar('block_type', 'D');
        }
        $newid = 0;
        if ($clone->store()) {
            $newid = $clone->id();  //get the id of the cloned block
        }
        if (!$newid) {
            //            \xoops_cp_header();
            $clone->getHtmlErrors();
            \xoops_cp_footer();
            exit();
        }
        if ('' !== $clone->getVar('template')) {
            /** @var \XoopsTplfileHandler $tplfileHandler */
            $tplfileHandler = xoops_getHandler('tplfile');
            $config         = self::runtimeGlobal('xoopsConfig');
            $btemplate      = $tplfileHandler->find($config['template_set'], 'block', (string)$bid);
            if (\count($btemplate) > 0) {
                $tplclone = $btemplate[0]->xoopsClone();
                $tplclone->setVar('tpl_id', 0);
                $tplclone->setVar('tpl_refid', $newid);
                $tplfileHandler->insert($tplclone);
            }
        }

        foreach ($bmodule as $bmid) {
            $sql = 'INSERT INTO ' . $this->db->prefix('block_module_link') . ' (block_id, module_id) VALUES (' . (int) $newid . ', ' . (int) $bmid . ')';
            $this->db->exec($sql);
        }
        foreach ($groups as $iValue) {
            $sql = 'INSERT INTO ' . $this->db->prefix('group_permission') . ' (gperm_groupid, gperm_itemid, gperm_modid, gperm_name) VALUES (' . (int) $iValue . ', ' . (int) $newid . ", 1, 'block_read')";
            $this->db->exec($sql);
        }
        $this->helper->redirect('admin/blocksadmin.php?op=list', 1, _AM_DBUPDATED);
    }

    /**
     * @param int|string $bid
     * @param int|string $title
     * @param int|string $weight
     * @param int|string $visible
     * @param int|string $side
     * @param int|string $bcachetime
     * @param array|null $bmodule
     */
    public function setOrder(int|string $bid, int|string $title, int|string $weight, int|string $visible, int|string $side, int|string $bcachetime, ?array $bmodule = null): void
    {
        $myblock = new \XoopsBlock((int)$bid);
        $myblock->setVar('title', (string)$title);
        $myblock->setVar('weight', (int)$weight);
        $myblock->setVar('visible', (int)$visible);
        $myblock->setVar('side', (int)$side);
        $myblock->setVar('bcachetime', (int)$bcachetime);
        $myblock->store();
        //        /** @var \XoopsBlockHandler $blockHandler */
        //        $blockHandler = \xoops_getHandler('block');
        //        return $blockHandler->insert($myblock);
    }

    /**
     * @param int $bid
     * @return void
     */
    public function editBlock(int $bid): void
    {
        //        require_once \dirname(__DIR__,2) . '/admin/admin_header.php';
        //        \xoops_cp_header();

        //        mpu_adm_menu();
        $myblock = new \XoopsBlock($bid);
        $sql     = 'SELECT module_id FROM ' . $this->db->prefix('block_module_link') . ' WHERE block_id=' . $bid;
        $result  = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            \trigger_error("Query Failed! SQL: $sql Error: " . $this->db->error(), \E_USER_ERROR);
        }
        $modules = [];
        while (false !== ($row = (($this->db->isResultSet($result) && ($result instanceof \mysqli_result)) ? $this->db->fetchArray($result) : false))) {
            $modules[] = (int)$row['module_id'];
        }

        $isCustom = \in_array($myblock->getVar('block_type'), ['C', 'E']);
        $block    = [
            'title'      => $myblock->getVar('title'),
            'form_title' => \_AM_SYSTEM_BLOCKS_EDITBLOCK,
            //        'name'       => $myblock->getVar('name'),
            'side'       => $myblock->getVar('side'),
            'weight'     => $myblock->getVar('weight'),
            'visible'    => $myblock->getVar('visible'),
            'content'    => $myblock->getVar('content', 'N'),
            'modules'    => $modules,
            'is_custom'  => $isCustom,
            'ctype'      => $myblock->getVar('c_type'),
            'bcachetime' => $myblock->getVar('bcachetime'),
            'op'         => 'edit_ok',
            'bid'        => $myblock->getVar('bid'),
            'edit_form'  => $myblock->getOptions(),
            'template'   => $myblock->getVar('template'),
            'options'    => $myblock->getVar('options'),
        ];
        echo '<a href="blocksadmin.php">' . \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'BADMIN') . '</a>&nbsp;<span style="font-weight:bold;">&raquo;&raquo;</span>&nbsp;' . \_AM_SYSTEM_BLOCKS_EDITBLOCK . '<br><br>';

        $this->render($block);
    }

    /**
     * @param int        $bid
     * @param string     $btitle
     * @param string     $bside
     * @param string     $bweight
     * @param string     $bvisible
     * @param string     $bcachetime
     * @param array|null $bmodule
     * @param array|null $options
     * @param array|null $groups
     */
    public function updateBlock(int $bid, string $btitle, string $bside, string $bweight, string $bvisible, string $bcachetime, ?array $bmodule, ?array $options, ?array $groups): void
    {
        $this->assertToken();

        $myblock = new \XoopsBlock($bid);
        $myblock->setVar('title', $btitle);
        $myblock->setVar('weight', $bweight);
        $myblock->setVar('visible', $bvisible);
        $myblock->setVar('side', $bside);
        $myblock->setVar('bcachetime', $bcachetime);
        //update block options
        if (isset($options)) {
            $optionsCount = \count($options);
            if ($optionsCount > 0) {
                //Convert array values to comma-separated
                foreach ($options as $i => $iValue) {
                    if (\is_array($iValue)) {
                        $options[$i] = \implode(',', $iValue);
                    }
                }
                $options = \implode('|', $options);
                $myblock->setVar('options', $options);
            }
        }
        $myblock->store();
        //        /** @var \XoopsBlockHandler $blockHandler */
        //        $blockHandler = \xoops_getHandler('block');
        //        $blockHandler->insert($myblock);

        if (!empty($bmodule) && \count($bmodule) > 0) {
            $sql = \sprintf('DELETE FROM `%s` WHERE block_id = %u', $this->db->prefix('block_module_link'), $bid);
            $this->db->exec($sql);
            if (\in_array(0, $bmodule)) {
                $sql = \sprintf('INSERT INTO `%s` (block_id, module_id) VALUES (%u, %d)', $this->db->prefix('block_module_link'), $bid, 0);
                $this->db->exec($sql);
            } else {
                foreach ($bmodule as $bmid) {
                    $sql = \sprintf('INSERT INTO `%s` (block_id, module_id) VALUES (%u, %d)', $this->db->prefix('block_module_link'), $bid, (int)$bmid);
                    $this->db->exec($sql);
                }
            }
        }
        // Scoped like orderBlock(): other modules' permissions can share this item id.
        $sql = \sprintf("DELETE FROM `%s` WHERE gperm_itemid = %u AND gperm_modid = 1 AND gperm_name = 'block_read'", $this->db->prefix('group_permission'), $bid);
        $this->db->exec($sql);
        if (!empty($groups)) {
            foreach ($groups as $grp) {
                $sql = \sprintf("INSERT INTO `%s` (gperm_groupid, gperm_itemid, gperm_modid, gperm_name) VALUES (%u, %u, 1, 'block_read')", $this->db->prefix('group_permission'), $grp, $bid);
                $this->db->exec($sql);
            }
        }
        $this->helper->redirect('admin/blocksadmin.php', 1, \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'UPDATE_SUCCESS'));
    }

    /**
     * @param array $bid
     * @param array $oldtitle
     * @param array $oldside
     * @param array $oldweight
     * @param array $oldvisible
     * @param array $oldgroups
     * @param array $oldbcachetime
     * @param array $oldbmodule
     * @param array $title
     * @param array $weight
     * @param array $visible
     * @param array $side
     * @param array $bcachetime
     * @param array $groups
     * @param array $bmodule
     */
    public function orderBlock(
        array $bid,
        array $oldtitle,
        array $oldside,
        array $oldweight,
        array $oldvisible,
        array $oldgroups,
        array $oldbcachetime,
        array $oldbmodule,
        array $title,
        array $weight,
        array $visible,
        array $side,
        array $bcachetime,
        array $groups,
        array $bmodule
    ): void {
        $this->assertToken();
        foreach (\array_keys($bid) as $i) {
            $blockId = (int)$bid[$i];
            if ($blockId <= 0) {
                continue;
            }

            $this->setOrder(
                $blockId,
                $this->preserveTitleToken((string) ($oldtitle[$i] ?? ''), (string) ($title[$i] ?? '')),
                $weight[$i] ?? 0,
                $visible[$i] ?? 0,
                $side[$i] ?? 0,
                $bcachetime[$i] ?? 0,
                $bmodule[$i] ?? []
            );

            if (!empty($bmodule[$i]) && \is_array($bmodule[$i])) {
                $sql = \sprintf('DELETE FROM `%s` WHERE block_id = %u', $this->db->prefix('block_module_link'), $blockId);
                $this->db->exec($sql);
                if (\in_array(0, $bmodule[$i], true)) {
                    $sql = \sprintf('INSERT INTO `%s` (block_id, module_id) VALUES (%u, %d)', $this->db->prefix('block_module_link'), $blockId, 0);
                    $this->db->exec($sql);
                } else {
                    foreach ($bmodule[$i] as $bmid) {
                        $sql = \sprintf('INSERT INTO `%s` (block_id, module_id) VALUES (%u, %d)', $this->db->prefix('block_module_link'), $blockId, (int)$bmid);
                        $this->db->exec($sql);
                    }
                }
            }
            $sql = \sprintf("DELETE FROM `%s` WHERE gperm_itemid = %u AND gperm_modid = 1 AND gperm_name = 'block_read'", $this->db->prefix('group_permission'), $blockId);
            $this->db->exec($sql);
            if (!empty($groups[$i]) && \is_array($groups[$i])) {
                foreach ($groups[$i] as $grp) {
                    $sql = \sprintf("INSERT INTO `%s` (gperm_groupid, gperm_itemid, gperm_modid, gperm_name) VALUES (%u, %u, 1, 'block_read')", $this->db->prefix('group_permission'), $grp, $blockId);
                    $this->db->exec($sql);
                }
            }
        }

        $this->helper->redirect('admin/blocksadmin.php', 1, \constant('_CO_' . $this->moduleDirNameUpper . '_' . 'UPDATE_SUCCESS'));
    }

    /**
     * @param array|null $block
     * @return void
     */
    public function render(?array $block = null): void
    {
        \xoops_load('XoopsFormLoader');
        \xoops_loadLanguage('common', $this->moduleDirName);

        $form = new \XoopsThemeForm($block['form_title'], 'blockform', 'blocksadmin.php', 'post', true);
        if (isset($block['name'])) {
            $form->addElement(new \XoopsFormLabel(\_AM_SYSTEM_BLOCKS_NAME, $block['name']));
        }
        $sideSelect = new \XoopsFormSelect(\_AM_SYSTEM_BLOCKS_TYPE, 'bside', $block['side']);
        $sideSelect->addOptionArray([
                                        0 => \_AM_SYSTEM_BLOCKS_SBLEFT,
                                        1 => \_AM_SYSTEM_BLOCKS_SBRIGHT,
                                        3 => \_AM_SYSTEM_BLOCKS_CBLEFT,
                                        4 => \_AM_SYSTEM_BLOCKS_CBRIGHT,
                                        5 => \_AM_SYSTEM_BLOCKS_CBCENTER,
                                        7 => \_AM_SYSTEM_BLOCKS_CBBOTTOMLEFT,
                                        8 => \_AM_SYSTEM_BLOCKS_CBBOTTOMRIGHT,
                                        9 => \_AM_SYSTEM_BLOCKS_CBBOTTOM,
                                    ]);
        $form->addElement($sideSelect);
        $form->addElement(new \XoopsFormText(\constant('_CO_' . $this->moduleDirNameUpper . '_' . 'WEIGHT'), 'bweight', 2, 5, $block['weight']));
        $form->addElement(new \XoopsFormRadioYN(\constant('_CO_' . $this->moduleDirNameUpper . '_' . 'VISIBLE'), 'bvisible', $block['visible']));
        $modSelect = new \XoopsFormSelect(\constant('_CO_' . $this->moduleDirNameUpper . '_' . 'VISIBLEIN'), 'bmodule', $block['modules'], 5, true);
        /** @var \XoopsModuleHandler $moduleHandler */
        $moduleHandler = xoops_getHandler('module');
        $criteria      = new \CriteriaCompo(new \Criteria('hasmain', '1'));
        $criteria->add(new \Criteria('isactive', '1'));
        $moduleList     = $moduleHandler->getList($criteria);
        $moduleList[-1] = \_AM_SYSTEM_BLOCKS_TOPPAGE;
        $moduleList[0]  = \_AM_SYSTEM_BLOCKS_ALLPAGES;
        \ksort($moduleList);
        $modSelect->addOptionArray($moduleList);
        $form->addElement($modSelect);
        $form->addElement(new \XoopsFormText(\_AM_SYSTEM_BLOCKS_TITLE, 'btitle', 50, 255, $block['title']), false);
        if ($block['is_custom']) {
            $textarea = new \XoopsFormDhtmlTextArea(\_AM_SYSTEM_BLOCKS_CONTENT, 'bcontent', $block['content'], 15, 70);
            $textarea->setDescription('<span style="font-size:x-small;font-weight:bold;">' . \_AM_SYSTEM_BLOCKS_USEFULTAGS . '</span><br><span style="font-size:x-small;font-weight:normal;">' . \sprintf(_AM_BLOCKTAG1, '{X_SITEURL}', XOOPS_URL . '/') . '</span>');
            $form->addElement($textarea, true);
            $ctypeSelect = new \XoopsFormSelect(\_AM_SYSTEM_BLOCKS_CTYPE, 'bctype', $block['ctype']);
            $ctypeSelect->addOptionArray([
                                             'H' => \_AM_SYSTEM_BLOCKS_HTML,
                                             'P' => \_AM_SYSTEM_BLOCKS_PHP,
                                             'S' => \_AM_SYSTEM_BLOCKS_AFWSMILE,
                                             'T' => \_AM_SYSTEM_BLOCKS_AFNOSMILE,
                                         ]);
            $form->addElement($ctypeSelect);
        } else {
            if ('' !== $block['template']) {
                /** @var \XoopsTplfileHandler $tplfileHandler */
                $tplfileHandler = xoops_getHandler('tplfile');
                $config         = self::runtimeGlobal('xoopsConfig');
                $btemplate      = $tplfileHandler->find($config['template_set'], 'block', $block['bid']);
                if (\count($btemplate) > 0) {
                    $form->addElement(new \XoopsFormLabel(\_AM_SYSTEM_BLOCKS_CONTENT, '<a href="' . Url::module('system', 'admin.php?fct=tplsets&amp;op=edittpl&amp;id=' . $btemplate[0]->getVar('tpl_id')) . '">' . \_AM_SYSTEM_BLOCKS_EDITTPL . '</a>'));
                } else {
                    $btemplate2 = $tplfileHandler->find('default', 'block', $block['bid']);
                    if (\count($btemplate2) > 0) {
                        $form->addElement(new \XoopsFormLabel(\_AM_SYSTEM_BLOCKS_CONTENT, '<a href="' . Url::module('system', 'admin.php?fct=tplsets&amp;op=edittpl&amp;id=' . $btemplate2[0]->getVar('tpl_id')) . '" target="_blank">' . \_AM_SYSTEM_BLOCKS_EDITTPL . '</a>'));
                    }
                }
            }
            if (false !== $block['edit_form']) {
                $form->addElement(new \XoopsFormLabel(\_AM_SYSTEM_BLOCKS_OPTIONS, $block['edit_form']));
            }
        }
        $cache_select = new \XoopsFormSelect(\_AM_SYSTEM_BLOCKS_BCACHETIME, 'bcachetime', $block['bcachetime']);
        $cache_select->addOptionArray([
                                          0       => \_NOCACHE,
                                          30      => \sprintf(\_SECONDS, 30),
                                          60      => \_MINUTE,
                                          300     => \sprintf(\_MINUTES, 5),
                                          1800    => \sprintf(\_MINUTES, 30),
                                          3600    => \_HOUR,
                                          18000   => \sprintf(\_HOURS, 5),
                                          86400   => \_DAY,
                                          259200  => \sprintf(\_DAYS, 3),
                                          604800  => \_WEEK,
                                          2592000 => \_MONTH,
                                      ]);
        $form->addElement($cache_select);

        /** @var \XoopsGroupPermHandler $grouppermHandler */
        $grouppermHandler = xoops_getHandler('groupperm');
        $groups           = $grouppermHandler->getGroupIds('block_read', $block['bid']);

        $form->addElement(new \XoopsFormSelectGroup(\_AM_SYSTEM_BLOCKS_GROUP, 'groups', true, $groups, 5, true));

        if (isset($block['bid'])) {
            $form->addElement(new \XoopsFormHidden('bid', $block['bid']));
        }
        $form->addElement(new \XoopsFormHidden('op', $block['op']));
        $form->addElement(new \XoopsFormHidden('fct', 'blocksadmin'));
        $buttonTray = new \XoopsFormElementTray('', '&nbsp;');
        if ($block['is_custom']) {
            $buttonTray->addElement(new \XoopsFormButton('', 'previewblock', \_PREVIEW, 'submit'));
        }

        //Submit buttons
        $buttonTray   = new \XoopsFormElementTray('', '');
        $submitButton = new \XoopsFormButton('', 'submitblock', \_SUBMIT, 'submit');
        $buttonTray->addElement($submitButton);

        $cancelButton = new \XoopsFormButton('', '', \_CANCEL, 'button');
        $cancelButton->setExtra('onclick="history.go(-1)"');
        $buttonTray->addElement($cancelButton);

        $form->addElement($buttonTray);
        $form->display();
    }

    /**
     * The list shows a stored `_MI_*` token resolved; if the admin submits that
     * resolved label unchanged, keep the token so the block title stays translatable.
     */
    private function preserveTitleToken(string $storedTitle, string $submittedTitle): string
    {
        if (
            $submittedTitle === $storedTitle
            || !\class_exists(\Xmf\I18n\LabelResolver::class)
            || !\Xmf\I18n\LabelResolver::isToken($storedTitle)
        ) {
            return $submittedTitle;
        }

        return $submittedTitle === (string) \Xmf\I18n\LabelResolver::resolve($storedTitle, $this->moduleDirName)
            ? $storedTitle
            : $submittedTitle;
    }

    /**
     * Every write path (order, edit, clone, delete) validates the XOOPS token here and fails
     * closed when the security service is missing. XoopsSecurity::check() consumes the token,
     * so a consumer's blocksadmin.php must not call check() itself before delegating.
     */
    private function assertToken(): void
    {
        $security = self::runtimeGlobal('xoopsSecurity');
        if (\is_object($security) && \method_exists($security, 'check') && $security->check()) {
            return;
        }
        $errors = \is_object($security) && \method_exists($security, 'getErrors') ? (array) $security->getErrors() : [];
        \redirect_header(
            Request::getString('SCRIPT_NAME', '', 'SERVER'),
            3,
            [] === $errors ? (\defined('_NOPERM') ? \_NOPERM : 'Security token missing.') : \implode('<br>', \array_map(\strval(...), $errors))
        );
        exit;
    }

    /** @legacy-global-accessor */
    private static function runtimeGlobal(string $name): mixed
    {
        return $GLOBALS[$name] ?? null;
    }
}
