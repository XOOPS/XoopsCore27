<?php
/**
 * A stand-in admin GUI for CpHeaderLateTemplateTest.
 *
 * XoopsSystemCpanel loads the GUI named by the 'cpanel' preference. Here that
 * preference is "cpheaderfake", so the real xoops_cp_header() and the real
 * XoopsSystemCpanel run against this class instead of a full admin theme.
 * Load it only in a separate process: it declares xoops_getConfigOption(),
 * which include/functions.php also declares.
 */

declare(strict_types=1);

if (!function_exists('xoops_getConfigOption')) {
    function xoops_getConfigOption($option, $type = 'XOOPS_CONF')
    {
        return 'cpanel' === $option ? 'cpheaderfake' : false;
    }
}

final class XoopsGuiCpheaderfake
{
    public static int $headers = 0;

    public $foldername;

    public $xoTheme;

    public static function validate(): bool
    {
        return true;
    }

    /**
     * What XoopsSystemGui::header() does with template_main: prefix it, then
     * build the theme with it as the content template.
     */
    public function header(): void
    {
        ++self::$headers;
        $template = $GLOBALS['xoopsOption']['template_main'] ?? '';
        if ('' !== $template && !str_contains($template, ':')) {
            $template                                = 'db:' . $template;
            $GLOBALS['xoopsOption']['template_main'] = $template;
        }
        $this->xoTheme                  = new stdClass();
        $this->xoTheme->contentTemplate = $template;
        $GLOBALS['xoTheme']             = $this->xoTheme;
    }
}
