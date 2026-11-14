<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/**
 * @category     Module
 * @package      mtools
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @copyright    https://xoops.org 2000-2026 &copy; XOOPS Project
 * @author       ZySpec <zyspec@yahoo.com>
 * @author       Mamba <mambax7@gmail.com>
 */

use Xmf\Request;
use Xoops\Helpers\Utility\HtmlBuilder;
use Xoops\ModuleTools\Internal\Presentation\SortControlBuilder;

/**
 * Admin/UI output helpers extracted from {@see SysUtility}.
 *
 * These produce HTML or write to theme/template globals, so they stay in mtools as a
 * legacy-UI facade and are NOT promoted to XMF. They are deliberately context-EXPLICIT:
 * the consumer's module helper is passed in, rather than resolved via late static
 * binding, so the methods behave identically no matter which class forwards to them.
 * {@see SysUtility::selectSorting()} / {@see SysUtility::getEditor()} resolve the
 * consumer Helper and forward here, preserving the old globals-friendly call style.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
class Output
{
    /**
     * Render the ascending/descending sort switch for a list column.
     *
     * @param string             $text      label shown before the sort arrows
     * @param string             $form_sort the column this control sorts by
     * @param \Xmf\Module\Helper $helper    the CONSUMER's module helper (icons/url)
     */
    public static function selectSorting($text, $form_sort, $helper): string
    {
        global $start, $order, $file_cat, $sort, $xoopsModule;

        $pathModIcon16 = $helper->url($helper->getModule()->getInfo('modicons16'));

        $model = new SortControlBuilder()->build(
            requestUri: Request::getString('REQUEST_URI', '', 'SERVER'),
            scriptName: Request::getString('SCRIPT_NAME', '', 'SERVER'),
            start: (int) ($start ?? 0), // a page that keeps $start local leaves the global null
            currentOrder: (string) $order,
            currentSort: (string) $sort,
            requestedSort: (string) $form_sort,
        );
        // Everything here derives from the request (REQUEST_URI, sort/order) or the caller: escape at emission.
        $escape = static fn (mixed $value): string => HtmlBuilder::escape((string) $value);
        $select_view = '<form name="form_switch" id="form_switch" action="' . $escape($model->formAction) . '" method="post"><span style="font-weight: bold;">' . $escape($text) . '</span>';
        $select_view .= '  <a href="' . $escape($model->ascendingUrl) . '"><img src="' . $escape($pathModIcon16 . '/' . $model->ascendingIcon) . '" title="ASC" alt="ASC"></a>';
        $select_view .= '<a href="' . $escape($model->descendingUrl) . '"><img src="' . $escape($pathModIcon16 . '/' . $model->descendingIcon) . '" title="DESC" alt="DESC"></a>';
        $select_view .= '</form>';

        return $select_view;
    }

    /**
     * Build a description editor element for the CONSUMER's module.
     *
     * @param \Xmf\Module\Helper $helper  the CONSUMER's module helper (editor config)
     * @param array|null         $options editor options; sensible defaults when null
     * @return \XoopsFormDhtmlTextArea|\XoopsFormEditor
     */
    public static function getEditor($helper, $options = null)
    {
        if (null === $options) {
            $options           = [];
            $options['name']   = 'Editor';
            $options['value']  = 'Editor';
            $options['rows']   = 10;
            $options['cols']   = '100%';
            $options['width']  = '100%';
            $options['height'] = '400px';
        }

        $isAdmin = $helper->isUserAdmin();

        if (\class_exists('XoopsFormEditor')) {
            if ($isAdmin) {
                $descEditor = new \XoopsFormEditor(\ucfirst($options['name']), $helper->getConfig('editorAdmin'), $options, $nohtml = false, $onfailure = 'textarea');
            } else {
                $descEditor = new \XoopsFormEditor(\ucfirst($options['name']), $helper->getConfig('editorUser'), $options, $nohtml = false, $onfailure = 'textarea');
            }
        } else {
            $descEditor = new \XoopsFormDhtmlTextArea(\ucfirst($options['name']), $options['name'], $options['value'], 100, 100);
        }

        return $descEditor;
    }

    /**
     * @param string $content text whose stripped tags become the page meta keywords
     */
    public static function metaKeywords($content): void
    {
        global $xoopsTpl, $xoTheme;
        $myts    = \MyTextSanitizer::getInstance();
        $content = $myts->undoHtmlSpecialChars($myts->displayTarea($content));
        if (null !== $xoTheme && \is_object($xoTheme)) {
            $xoTheme->addMeta('meta', 'keywords', \strip_tags($content));
        } else {    // Compatibility for old Xoops versions
            $xoopsTpl->assign('xoops_metaKeywords', \strip_tags($content));
        }
    }

    /**
     * @param string $content text whose stripped tags become the page meta description
     */
    public static function metaDescription($content): void
    {
        global $xoopsTpl, $xoTheme;
        $myts    = \MyTextSanitizer::getInstance();
        $content = $myts->undoHtmlSpecialChars($myts->displayTarea($content));
        if (null !== $xoTheme && \is_object($xoTheme)) {
            $xoTheme->addMeta('meta', 'description', \strip_tags($content));
        } else {    // Compatibility for old Xoops versions
            $xoopsTpl->assign('xoops_metaDescription', \strip_tags($content));
        }
    }

    /**
     * A local navigation target (relative or root-relative path), or $fallback when $path is not one.
     *
     * Leading whitespace, control characters, backslashes, a scheme and a host are all rejected
     * because browsers normalise them into a navigation ("  javascript:x" runs). The fallback is
     * validated the same way and ends at a fixed "index.php".
     */
    public static function localPath(string $path, string $fallback = 'index.php'): string
    {
        if (self::isLocalPath($path)) {
            return $path;
        }

        return self::isLocalPath($fallback) ? $fallback : 'index.php';
    }

    /**
     * A link target that cannot run script: an http(s) URL or a local path (see localPath()),
     * else $fallback. Escaping the href does not stop a "javascript:" destination; this does.
     */
    public static function navigationTarget(string $url, string $fallback = 'index.php'): string
    {
        return 1 === \preg_match('#^https?://#i', $url) ? $url : self::localPath($url, $fallback);
    }

    private static function isLocalPath(string $path): bool
    {
        return '' !== $path
            && 1 !== \preg_match('/^\s|[\x00-\x1F\x7F]|\\\\/', $path)
            && !\str_starts_with($path, '//')
            && 1 !== \preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $path);
    }
}
