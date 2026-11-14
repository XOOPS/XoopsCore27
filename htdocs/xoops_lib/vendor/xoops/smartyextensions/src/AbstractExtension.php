<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions;

/**
 * Base class for all XOOPS Smarty extensions.
 *
 * Subclasses override getModifiers(), getFunctions(), and/or getBlockHandlers()
 * to declare their plugins. The register() method handles Smarty 4 registration
 * via registerPlugin(). For Smarty 5, use Smarty5Adapter to wrap extensions.
 *
 * ---
 *
 * Assign contract (default for all functions)
 * -------------------------------------------
 * Functions that accept an `assign` parameter MUST store the raw, unescaped
 * value in the template variable. HTML escaping is applied only on the
 * direct-output path (the return statement). This prevents double-escaping
 * when the caller interpolates the variable into an HTML attribute or applies
 * a Smarty modifier later.
 *
 * Correct pattern:
 *   if (!empty($params['assign'])) {
 *       $template->assign($params['assign'], $rawValue);  // raw
 *       return '';
 *   }
 *   return htmlspecialchars($rawValue, ENT_QUOTES, 'UTF-8');  // escaped
 *
 * Exception — HTML-generating functions
 * --------------------------------------
 * Functions whose entire purpose is to build and return an HTML string
 * (e.g. createButton, renderFormErrors, displayError) store already-escaped
 * HTML in the assigned variable. Their docblocks are marked explicitly:
 * "exception to the raw-in-assign contract". When consuming an assigned
 * variable from one of these functions, output it with `<{$var nofilter}>`
 * rather than `<{$var}>` or `<{$var|escape}>` to avoid double-escaping.
 *
 * Block handler contract
 * ----------------------
 * Block functions receive `$content` as already-rendered HTML from the inner
 * template (Smarty renders the block body before calling the handler on close).
 * Do not re-escape $content; treat it as trusted markup produced by the
 * template engine.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
abstract class AbstractExtension
{
    /**
     * @return array<string, callable> modifier name => callable
     */
    public function getModifiers(): array
    {
        return [];
    }

    /**
     * @return array<string, callable> function name => callable
     */
    public function getFunctions(): array
    {
        return [];
    }

    /**
     * @return array<string, callable> block name => callable
     */
    public function getBlockHandlers(): array
    {
        return [];
    }

    /**
     * Register all plugins with a Smarty 4 instance.
     *
     * Functions and blocks are registered non-cacheable, matching Smarty5Adapter:
     * their output can depend on the current user (xo_permission, CSRF tokens),
     * so a cached page must re-run them for every request.
     *
     * @param \Smarty|\Smarty\Smarty $smarty
     */
    public function register(object $smarty): void
    {
        foreach ($this->getModifiers() as $name => $callback) {
            $smarty->registerPlugin('modifier', $name, $callback);
        }
        foreach ($this->getFunctions() as $name => $callback) {
            $smarty->registerPlugin('function', $name, $callback, false);
        }
        foreach ($this->getBlockHandlers() as $name => $callback) {
            $smarty->registerPlugin('block', $name, $callback, false);
        }
    }

    /**
     * True when a URL is safe to place in a navigation attribute (href, src, action).
     *
     * Allows http(s)/ftp/mailto, protocol-relative, and scheme-less relative URLs.
     * Any colon before the first / ? # is treated as a scheme and rejected, which
     * also covers javascript:, data:, vbscript: and whitespace/control-padded forms.
     * Validate the exact string the browser will see: callers escape it afterwards,
     * so the browser decodes the attribute back to this value.
     */
    protected static function isSafeUrl(string $url): bool
    {
        if (\preg_match('#^(?:https?://|ftp://|mailto:)#i', $url)) {
            return true;
        }

        return !\str_contains(\substr($url, 0, \strcspn($url, '/?#')), ':');
    }
}
