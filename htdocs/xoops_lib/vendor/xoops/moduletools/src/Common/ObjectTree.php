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

// Loaded from XOOPS (XOOPS_ROOT_PATH set) or from a harness that stubs \XoopsObjectTree; there is
// deliberately no fallback class, because a fallback loaded once would shadow the real one for the
// rest of the process.
if (!\class_exists(\XoopsObjectTree::class, false) && \defined('XOOPS_ROOT_PATH')) {
    require_once \XOOPS_ROOT_PATH . '/class/tree.php';
}

/**
 * Form element that ...
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
class ObjectTree extends \XoopsObjectTree
{
    /**
     * Make options for a select box from
     *
     * @param string       $fieldName    Name of the member variable from the node objects that should be used as the title for the options.
     * @param int          $key          ID of the object to display as the root of select options
     * @param array<int|string, string> $optionsArray Result from previous recursions
     * @param string       $prefix_orig  String to indent items at deeper levels
     * @param string       $prefix_curr  String to indent the current item
     *
     * @return array<int|string, string>
     */
    public function makeSelBoxOptionsArray($fieldName, $key, &$optionsArray, $prefix_orig, $prefix_curr = '')
    {
        if ($key > 0) {
            $value                = $this->tree[$key]['obj']->getVar($this->myId);
            $optionsArray[$value] = $prefix_curr . $this->tree[$key]['obj']->getVar($fieldName);
            $prefix_curr          .= $prefix_orig;
        }
        if (isset($this->tree[$key]['child']) && !empty($this->tree[$key]['child'])) {
            foreach ($this->tree[$key]['child'] as $childkey) {
                $this->makeSelBoxOptionsArray($fieldName, $childkey, $optionsArray, $prefix_orig, $prefix_curr);
            }
        }

        return $optionsArray;
    }

    /**
     * Legacy options-array contract retained for ModuleTools 1.x consumers.
     *
     * Use core's XoopsObjectTree when an HTML select is required.
     *
     * @param string $name           Name of the select element
     * @param string $fieldName      Member variable of the node objects used as the option title
     * @param string $prefix         String to indent deeper levels
     * @param string $selected       Value to display as selected
     * @param bool   $addEmptyOption Add an empty option with value "0" at the top
     * @param int    $key            ID of the object to use as the root
     * @param string $extra          Extra attributes for the select element
     * @return array<int|string, string> Value => indented-title pairs
     */
    // @phpstan-ignore method.childReturnType (Released ModuleTools 1.x promises an array despite core's HTML contract.)
    public function makeSelBox($name, $fieldName, $prefix = '-', $selected = '', $addEmptyOption = false, $key = 0, $extra = '')
    {
        return $this->makeOptionsArray($fieldName, $prefix, $addEmptyOption, $key);
    }

    /**
     * Value => indented-title pairs for the tree, ready for XoopsFormSelect::addOptionArray().
     *
     * makeSelBox() retains the same array result for legacy callers.
     *
     * @param string $fieldName      Member variable of the node objects used as the option title
     * @param string $prefix         String to indent deeper levels
     * @param bool   $addEmptyOption Add an empty option with value "0" at the top
     * @param int    $key            ID of the object to use as the root
     * @return array<int|string, string>
     */
    public function makeOptionsArray(string $fieldName, string $prefix = '-', bool $addEmptyOption = false, int $key = 0): array
    {
        $optionsArray = [];
        if ($addEmptyOption) {
            $optionsArray[0] = '';
        }

        return $this->makeSelBoxOptionsArray($fieldName, $key, $optionsArray, $prefix);
    }
}
