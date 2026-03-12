<?php
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

include_once $GLOBALS['xoops']->path('class/tree.php');

/**
 * SystemMenusTree : extension utilitaire de XoopsObjectTree pour menus
 */
class SystemMenusTree extends XoopsObjectTree
{
    /**
     * @access private
     */
    protected $listTree = array();
    protected $cpt;

    /**
     * Constructor
     *
     * @param array  $objectArr Array of {@link XoopsObject}s
     * @param string $myId      field name of object ID
     * @param string $parentId  field name of parent object ID
     * @param string $rootId    field name of root object ID
     */
    public function __construct(&$objectArr, $myId, $parentId, $rootId = null)
    {
        $this->cpt = 0;
        parent::__construct($objectArr, $myId, $parentId, $rootId);
    }

    /**
     * Make a select box with options from the tree
     *
     * @param  string  $fieldName      Name of the member variable from the
     *                                 node objects that should be used as the title for the options.
     * @param  string  $prefix         String to indent deeper levels
     *
     * @return array   $listTree       Tree
     */
    public function makeTree(
        $fieldName,
        $prefix = '-',
        $productid = 0
    ) {
        $this->addTree($fieldName, $productid, 0, $prefix);

        return $this->listTree;
    }

    /**
     * Make options for a select box from
     *
     * @param string          $fieldName   Name of the member variable from the node objects that
     *                                     should be used as the title for the options.
     * @param int             $key         ID of the object to display as the root of select options
     * @param string          $prefix_orig String to indent items at deeper levels
     * @param string          $prefix_curr String to indent the current item
     *
     * @return void
     * @access private
     */
    protected function addTree($fieldName, $itemid, $key, $prefix_orig, $prefix_curr = '', $level = 1)
    {
        if ($key > 0) {
            if (($itemid == $this->tree[$key]['obj']->getVar('items_cid')) || $itemid == 0) {
                $value = $this->tree[$key]['obj']->getVar('items_id');
                $name = $prefix_curr . ' ' . $this->tree[$key]['obj']->getVar($fieldName);
                $prefix_curr .= $prefix_orig;
                $this->listTree[$this->cpt]['name'] = $name;
                $this->listTree[$this->cpt]['id'] = $value;
                $this->listTree[$this->cpt]['level'] = $level;
                $this->listTree[$this->cpt]['obj'] = $this->tree[$key]['obj'];
                $this->cpt++;
                $level++;
            }
        }
        if (isset($this->tree[$key]['child']) && !empty($this->tree[$key]['child'])) {
            foreach ($this->tree[$key]['child'] as $childKey) {
                $this->addTree($fieldName, $itemid, $childKey, $prefix_orig, $prefix_curr, $level);
            }
        }
    }
}
