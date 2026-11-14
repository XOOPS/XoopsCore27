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

use Xmf\Yaml;
use Xoops\Helpers\Utility\HtmlBuilder;
use Xoops\ModuleTools\Internal\PackageLanguage;

/**
 * Class TestdataButtons
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
class TestdataButtons
{
    /** Button status constants */
    private const int SHOW_BUTTONS = 1;
    private const int HIDE_BUTTONS = 0;

    /** Emit the button CSS only once per request. */
    private static bool $styleEmitted = false;

    /**
     * Give Xmf's `.xo-buttons a.ui-corner-all` links real button chrome. Some
     * cpanel admin themes don't ship the jQuery-UI styling these rely on, so the
     * buttons render as bare links; this makes them look like buttons everywhere.
     */
    private static function emitButtonStyle(): void
    {
        if (self::$styleEmitted) {
            return;
        }
        self::$styleEmitted = true;
        echo '<style>'
            . '.xo-buttons{margin:6px 0 14px}'
            . '.xo-buttons a{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;margin:0 0 6px;'
            . 'margin-inline-end:6px;border:1px solid #cdd5df;border-radius:6px;background:#f8f9fa;color:#212529;'
            . 'text-decoration:none;font-weight:500;line-height:1.2}'
            . '.xo-buttons a:hover{background:#eef1f4;text-decoration:none}'
            . '.xo-buttons img{vertical-align:middle;border:0}'
            . '</style>';
    }

    /**
     * Load the test button configuration
     *
     * @param \Xmf\Module\Admin $adminObject
     *
     * @return void
     */
    public static function loadButtonConfig(
        $adminObject,
        \Xmf\Module\Helper $helper,
        ?int $displayFlag = null,
    ): void {
        self::emitButtonStyle();

        // These shared labels belong to ModuleTools rather than to the consumer
        // module, so load the package catalog without inventing a module Helper.
        PackageLanguage::load('common', self::runtimeLanguage());

        $moduleDirName      = $helper->dirname();
        $moduleDirNameUpper = \mb_strtoupper($moduleDirName);

        // Config source: a consumer that keeps the toggle in XOOPS preferences passes
        // the flag in ($displayFlag); otherwise read its own config/admin.yml.
        if (null !== $displayFlag) {
            $displaySampleButton = $displayFlag;
        } else {
            $yamlFile            = $helper->path('/config/admin.yml');
            /** @var array $config */
            $config              = Yaml::readWrapped($yamlFile); // work with phpmyadmin YAML dumps
            $displaySampleButton = $config['displaySampleButton'] ?? self::HIDE_BUTTONS;
        }

        // CSRF token appended to the toggle links; validated in hide/showButtons() before
        // the config write. The toggle is a state change, so it must not be forgeable.
        // The request field name is "<tokenName>_REQUEST" (default token name XOOPS_TOKEN).
        // A raw '&': ModuleAdmin::renderButton() HTML-escapes the link, so '&amp;' here
        // arrived as an "amp;XOOPS_TOKEN_REQUEST" parameter and every token check failed.
        $tokenParam = '&XOOPS_TOKEN_REQUEST=' . self::runtimeSecurity()->createToken();

        if (self::SHOW_BUTTONS == $displaySampleButton) {
            \xoops_loadLanguage('admin/modulesadmin', 'system');
            // The data links carry the token too, so a testdata/index.php may validate it
            // directly (the reference endpoint additionally confirms load/clear via xoops_confirm()).
            $adminObject->addItemButton(\constant('_CO_MTOOLS_LOAD_SAMPLEDATA'), $helper->url('testdata/index.php?op=load' . $tokenParam), 'add');
            $adminObject->addItemButton(\constant('_CO_MTOOLS_SAVE_SAMPLEDATA'), $helper->url('testdata/index.php?op=save' . $tokenParam), 'add');
            $adminObject->addItemButton(\constant('_CO_MTOOLS_CLEAR_SAMPLEDATA'), $helper->url('testdata/index.php?op=clear' . $tokenParam), 'alert');
            //    $adminObject->addItemButton(constant('_CO_MTOOLS_EXPORT_SCHEMA'), $helper->url( 'testdata/index.php?op=exportschema'), 'add');
            $adminObject->addItemButton(\constant('_CO_MTOOLS_HIDE_SAMPLEDATA_BUTTONS'), '?op=hide_buttons' . $tokenParam, 'delete');
        } else {
            $adminObject->addItemButton(\constant('_CO_MTOOLS_SHOW_SAMPLEDATA_BUTTONS'), '?op=show_buttons' . $tokenParam, 'add');
            // $displaySampleButton = $config['displaySampleButton'];
        }
    }

    //$modhelper->url('admin/index.php?op=show_buttons')

    /**
     * Return self-contained, always-visible sample-data buttons as plain HTML.
     *
     * Unlike {@see loadButtonConfig()} (which depends on Xmf's admin item-button
     * rendering + jQuery-UI chrome that some cpanel themes don't ship — leaving
     * the buttons invisible), this echoes real, styled `<a>` buttons that render
     * in every admin theme. The consumer simply `echo`s the return value.
     *
     * @param \Xmf\Module\Helper $helper      consumer module helper
     * @param int|null           $displayFlag 1 = show the data buttons, 0 = show
     *                                         only the "Show" toggle; null = read
     *                                         the consumer's config/admin.yml.
     */
    public static function renderHtml(\Xmf\Module\Helper $helper, ?int $displayFlag = null): string
    {
        PackageLanguage::load('common', self::runtimeLanguage());

        if (null !== $displayFlag) {
            $show = self::SHOW_BUTTONS === $displayFlag;
        } else {
            /** @var array $config */
            $config = Yaml::readWrapped($helper->path('/config/admin.yml'));
            $show   = self::SHOW_BUTTONS === ($config['displaySampleButton'] ?? self::HIDE_BUTTONS);
        }

        $token = self::runtimeSecurity()->createToken();
        $btn   = static function (string $label, string $href, string $variant = ''): string {
            $cls = 'xo-sd-btn' . ('' !== $variant ? ' xo-sd-btn--' . $variant : '');
            return '<a class="' . $cls . '" href="' . $href . '">'
                . HtmlBuilder::escape($label) . '</a>';
        };

        $out = self::sampleDataStyle() . '<div class="xo-sampledata">';
        if ($show) {
            $out .= $btn(\constant('_CO_MTOOLS_LOAD_SAMPLEDATA'), $helper->url('testdata/index.php?op=load&amp;XOOPS_TOKEN_REQUEST=' . $token));
            $out .= $btn(\constant('_CO_MTOOLS_SAVE_SAMPLEDATA'), $helper->url('testdata/index.php?op=save&amp;XOOPS_TOKEN_REQUEST=' . $token));
            $out .= $btn(\constant('_CO_MTOOLS_CLEAR_SAMPLEDATA'), $helper->url('testdata/index.php?op=clear&amp;XOOPS_TOKEN_REQUEST=' . $token), 'danger');
            $out .= $btn(\constant('_CO_MTOOLS_HIDE_SAMPLEDATA_BUTTONS'), '?op=hide_buttons&amp;XOOPS_TOKEN_REQUEST=' . $token, 'muted');
        } else {
            $out .= $btn(\constant('_CO_MTOOLS_SHOW_SAMPLEDATA_BUTTONS'), '?op=show_buttons&amp;XOOPS_TOKEN_REQUEST=' . $token);
        }

        return $out . '</div>';
    }

    /** Self-contained button CSS (no theme / jQuery-UI dependency). */
    private static function sampleDataStyle(): string
    {
        if (self::$styleEmitted) {
            return '';
        }
        self::$styleEmitted = true;
        return '<style>'
            . '.xo-sampledata{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 16px;clear:both}'
            . '.xo-sd-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid #cdd5df;'
            . 'border-radius:7px;background:#fff;color:#1f2733 !important;font:600 13px system-ui,-apple-system,sans-serif;'
            . 'text-decoration:none;line-height:1.2;cursor:pointer}'
            . '.xo-sd-btn:hover{background:#f3f6fb;text-decoration:none}'
            . '.xo-sd-btn--danger{border-color:#f1aeb5;color:#b02a37 !important;background:#fff5f6}'
            . '.xo-sd-btn--danger:hover{background:#fdebec}'
            . '.xo-sd-btn--muted{color:#6b7280 !important}'
            . '</style>';
    }

    public static function hideButtons(\Xmf\Module\Helper $modhelper): void
    {
        if (!self::checkToken()) {
            \redirect_header($modhelper->url('admin/index.php'), 3, \implode('<br>', self::runtimeSecurity()->getErrors()));
            return;
        }
        $yamlFile                   = $modhelper->path('config/admin.yml');
        $app                        = [];
        $app['displaySampleButton'] = 0;
        Yaml::save($app, $yamlFile);
        \redirect_header($modhelper->url('admin/index.php'), 0, '');
    }

    public static function showButtons(\Xmf\Module\Helper $modhelper): void
    {
        if (!self::checkToken()) {
            \redirect_header($modhelper->url('admin/index.php'), 3, \implode('<br>', self::runtimeSecurity()->getErrors()));
            return;
        }
        $yamlFile                   = $modhelper->path('config/admin.yml');
        $app                        = [];
        $app['displaySampleButton'] = 1;
        Yaml::save($app, $yamlFile);
        \redirect_header($modhelper->url('admin/index.php'), 0, '');
    }

    /**
     * Toggle the sample-data buttons for a consumer that stores `displaySampleButton`
     * in its XOOPS module preferences (rather than mtools' config/admin.yml).
     *
     * CSRF-checked, then writes the module config row and redirects back.
     */
    public static function setSampleButtonConfig(\Xmf\Module\Helper $modhelper, int $value): void
    {
        if (!self::checkToken()) {
            \redirect_header($modhelper->url('admin/index.php'), 3, \implode('<br>', self::runtimeSecurity()->getErrors()));
            return;
        }
        $module = $modhelper->getModule();
        if (\is_object($module)) {
            $configHandler = xoops_getHandler('config');
            $criteria      = new \Criteria('conf_modid', (int) $module->getVar('mid'));
            foreach ($configHandler->getConfigs($criteria) as $cfg) {
                if ('displaySampleButton' === $cfg->getVar('conf_name')) {
                    $cfg->setVar('conf_value', $value);
                    $configHandler->insertConfig($cfg);
                    break;
                }
            }
        }
        \redirect_header($modhelper->url('admin/index.php'), 0, '');
    }

    /**
     * Validate the CSRF token carried on the toggle link.
     *
     * XoopsSecurity::check() (clearIfValid=true) reads XOOPS_TOKEN_REQUEST from POST then
     * GET, so it validates the one-time token carried on the GET toggle links produced by
     * loadButtonConfig().
     */
    public static function checkToken(): bool
    {
        $security = self::runtimeSecurity();

        return is_object($security) && $security->check();
    }

    /**
     * Whether the current request may run a destructive sample-data action: an
     * administrator session and a valid one-time token. Used by every state-changing
     * TestdataSample method, so a consumer's testdata/index.php cannot skip it.
     */
    public static function isAuthorizedRequest(): bool
    {
        return self::runtimeUserIsAdmin() && self::checkToken();
    }

    /** @legacy-global-accessor */
    private static function runtimeUserIsAdmin(): bool
    {
        return !empty($GLOBALS['xoopsUserIsAdmin']);
    }

    /** @legacy-global-accessor */
    private static function runtimeSecurity(): mixed
    {
        return $GLOBALS['xoopsSecurity'] ?? null;
    }

    /** @legacy-global-accessor */
    private static function runtimeLanguage(): string
    {
        $config = $GLOBALS['xoopsConfig'] ?? [];

        return is_array($config) ? (string) ($config['language'] ?? 'english') : 'english';
    }
}
