<?php
/**
 * XOOPS form container interface
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @subpackage          form
 * @since               2.7.0
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Contract for form elements that contain nested form elements.
 */
interface XoopsFormContainerInterface
{
    /**
     * Is this element a container of other elements?
     *
     * @return bool
     */
    public function isContainer();

    /**
     * Get an array of "required" form elements.
     *
     * @return XoopsFormElement[] array of {@link XoopsFormElement}s
     */
    public function &getRequired();

    /**
     * Get an array of form elements.
     *
     * @param bool $recurse get elements recursively?
     *
     * @return XoopsFormElement[] array of {@link XoopsFormElement}s
     */
    public function &getElements($recurse = false);
}
