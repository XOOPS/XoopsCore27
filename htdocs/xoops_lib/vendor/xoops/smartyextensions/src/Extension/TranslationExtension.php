<?php

declare(strict_types=1);

namespace Xoops\SmartyExtensions\Extension;

use Xoops\SmartyExtensions\AbstractExtension;

/**
 * XOOPS 2.8.0 i18n Smarty surface: `<{xoTrans}>`/`<{trans}>`/`<{xoT}>` and the
 * `xoTrans` modifier, backed by {@see \Xmf\I18n\Translation} when the engine
 * is initialized, degrading to a hardened legacy-constant lookup otherwise
 * (xmf 1.3.x sites — total no-op guarded via `class_exists(..., true)` and
 * {@see \Xmf\I18n\Translation::isInitialized()}).
 *
 * ESCAPING (ADR-003) — deliberate exception to {@see AbstractExtension}'s
 * escape-on-direct-output contract: the resolved CATALOG STRING is always
 * emitted RAW (translated strings legitimately carry markup — 24
 * markup-bearing core constants, 99-Addendum §2.1). What the TEMPLATE passes
 * in is escaped: the ad-hoc PLACEHOLDER VALUES and the `default=` text
 * (unless `escape=false` is passed), and a KEY that does not resolve
 * (always). The result is therefore HTML in every case, and the assigned
 * value (`assign=`) is that same HTML: print it without escaping it again.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
final class TranslationExtension extends AbstractExtension
{
    /** @var list<string> params consumed by xoTrans() itself, never treated as ad-hoc {name} placeholders */
    private const RESERVED_PARAMS = [
        'key', 'id', 'domain', 'file', 'dirname', 'params', 'count',
        'default', 'assign', 'locale', 'context', 'escape',
    ];

    public function __construct(private readonly bool $debug = false)
    {
    }

    public function getFunctions(): array
    {
        return [
            'xoTrans' => $this->xoTrans(...),
            'trans'   => $this->xoTrans(...),
            'xoT'     => $this->xoTrans(...),
        ];
    }

    public function getModifiers(): array
    {
        return [
            'xoTrans' => $this->xoTransModifier(...),
        ];
    }

    /**
     * Usage: <{xoTrans key="_MI_NEWS_TITLE" domain="news" assign="title"}>
     *        <{xoTrans key="_MI_NEWS_BY" name=$author}>
     *        <{xoTrans key="_MI_NEWS_MISSING" default="News"}>
     *
     * @param array<array-key,mixed> $params
     */
    public function xoTrans(array $params, object $template): string
    {
        $key = $this->toAttributeString($params['key'] ?? $params['id'] ?? '');
        if ('' === $key) {
            return '';
        }

        $domain = $this->toAttributeString($params['domain'] ?? $params['file'] ?? 'main');
        if (array_key_exists('file', $params) && !array_key_exists('domain', $params)) {
            $this->deprecateFileParam();
        }

        $dirname = $this->toAttributeString($params['dirname'] ?? '');

        $count = (array_key_exists('count', $params) && is_numeric($params['count'])) ? (int) $params['count'] : null;

        $values = $this->placeholderValues($params);
        $escape = $this->escapeEnabled($params['escape'] ?? null);
        if ($escape) {
            $values = $this->escapeValues($values);
        }

        $out = $this->lookup($key, $dirname, $domain, $values, $count);
        if (null === $out) {
            if (array_key_exists('default', $params)) {
                $default = $this->toAttributeString($params['default']);
                $out     = $escape ? $this->escape($default) : $default;
            } else {
                $out = $this->escape($key);
            }
        }

        if (!empty($params['assign']) && method_exists($template, 'assign')) {
            $template->assign($this->toAttributeString($params['assign']), $out);

            return '';
        }

        return $out;
    }

    /**
     * Placeholder values of a tag: the params= array, then every attribute
     * that is not reserved. locale= and context= are reserved no-ops: left
     * out here, never read or passed through.
     *
     * @param array<array-key,mixed> $params
     *
     * @return array<array-key,mixed>
     */
    private function placeholderValues(array $params): array
    {
        $values = is_array($params['params'] ?? null) ? $params['params'] : [];
        foreach ($params as $name => $value) {
            if (is_string($name) && !in_array($name, self::RESERVED_PARAMS, true)) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /**
     * Read the escape= attribute: only an explicit, recognized false turns
     * escaping off. An invalid or unrecognized value must never silently
     * disable it.
     */
    private function escapeEnabled(mixed $flag): bool
    {
        // Only a scalar is read. filter_var() would coerce a null to '',
        // a RECOGNIZED false, and wrongly disable escaping; an object
        // could throw while being converted and break rendering.
        return !is_scalar($flag)
            || false !== filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Same lookup as {@see xoTrans()}, without placeholders and without a
     * default. A key that does not resolve is returned HTML-escaped.
     *
     * Usage: <{"_MD_X"|xoTrans:'news'}>
     */
    public function xoTransModifier(string $key, string $dirname = '', string $domain = 'main'): string
    {
        return $this->lookup($key, $dirname, $domain) ?? $this->escape($key);
    }

    /**
     * Smarty 4 registration path (registerPlugin), non-clobbering: a
     * plugin name already registered under $smarty is left alone and the
     * collision is logged rather than silently overwritten or fatal.
     *
     * Under Smarty 5,
     * {@see \Xoops\SmartyExtensions\ExtensionRegistry::registerAll()} takes
     * the addExtension()/Smarty5Adapter path and this method is not called.
     *
     * The functions are registered non-cacheable, as in
     * {@see AbstractExtension::register()}: the result depends on the
     * visitor's language, so a cached page must run them again.
     *
     * @param \Smarty|\Smarty\Smarty $smarty
     */
    public function register(object $smarty): void
    {
        $registered = $smarty->registered_plugins ?? null;

        foreach (['function' => $this->getFunctions(), 'modifier' => $this->getModifiers()] as $type => $plugins) {
            $taken = is_array($registered) && is_array($registered[$type] ?? null) ? $registered[$type] : [];
            foreach ($plugins as $name => $callback) {
                if (isset($taken[$name])) {
                    self::logCollision($type, $name);
                    continue;
                }
                $smarty->registerPlugin($type, $name, $callback, 'function' !== $type);
            }
        }
    }

    /**
     * Smarty-5 probe: CoreExtension/DefaultExtension/BCPluginsAdapter are
     * pushed into the extension list before any addExtension() call, so a
     * name collision means OUR tag silently loses (Smarty resolves the
     * FIRST match) rather than failing loudly. Call this BEFORE
     * ExtensionRegistry::registerAll() and log any hit.
     *
     * @param \Smarty|\Smarty\Smarty $smarty
     *
     * @return list<string> e.g. ['function:xoTrans', 'modifier:xoTrans']
     */
    public static function probeCollisions(object $smarty): array
    {
        $hits = [];

        foreach (['xoTrans', 'trans', 'xoT'] as $name) {
            if (method_exists($smarty, 'getFunctionHandler') && null !== $smarty->getFunctionHandler($name)) {
                $hits[] = 'function:' . $name;
            }
        }

        if (method_exists($smarty, 'getModifierCallback') && null !== $smarty->getModifierCallback('xoTrans')) {
            $hits[] = 'modifier:xoTrans';
        }

        return $hits;
    }

    /**
     * The one lookup behind the tag and the modifier: the translation, or
     * null when the key does not resolve. An empty $dirname means the current
     * module; a miss is reported in debug mode.
     *
     * @param array<array-key,mixed> $values
     */
    private function lookup(string $key, string $dirname, string $domain, array $values = [], ?int $count = null): ?string
    {
        if ('' === $dirname && ($GLOBALS['xoopsModule'] ?? null) instanceof \XoopsModule) {
            $dirname = $this->toAttributeString($GLOBALS['xoopsModule']->getVar('dirname', 'n'));
        }

        if (class_exists(\Xmf\I18n\Translation::class) && \Xmf\I18n\Translation::isInitialized()) {
            $out = \Xmf\I18n\Translation::translate($dirname, $key, $values, $domain, $count);
            // The engine returns the key on a miss, and a translation may be
            // the same text as its key: only then is the engine asked.
            if ($out === $key && !$this->engineResolves($dirname, $key, $domain)) {
                $out = null;
            }
        } else {
            $out = $this->constantFallback($key, $values);
        }

        if (null === $out && $this->debug) {
            $this->fireMissingEvent($dirname, $domain, $key);
        }

        return $out;
    }

    private function engineResolves(string $dirname, string $key, string $domain): bool
    {
        try {
            return true === \Xmf\I18n\Translation::getInstance()?->explain($dirname, $key, $domain)->found();
        } catch (\Throwable) {
            // translate() already swallowed the same failure and returned the key.
            return false;
        }
    }

    /**
     * Legacy-constant degrade path used only when the Xmf\I18n engine is
     * absent or uninitialized. Like {@see XoopsCoreExtension::translate()},
     * it reads only language constants ({@see self::isLanguageConstant()}) —
     * never an arbitrary one (XOOPS_DB_PASS, XOOPS_ROOT_PATH, …).
     *
     * @param array<array-key,mixed> $values
     *
     * @return string|null null when $key is not a defined string language constant
     */
    private function constantFallback(string $key, array $values): ?string
    {
        if (!self::isLanguageConstant($key)) {
            return null;
        }
        $value = constant($key);
        if (!is_string($value)) {
            return null;
        }

        $map = [];
        foreach ($values as $name => $v) {
            if (!is_string($name)) {
                continue;
            }
            try {
                $map['{' . $name . '}'] = $this->stringify($v);
            } catch (\Throwable) {
                // A throwing Stringable::__toString() must never break
                // rendering — leave this token untouched instead.
                continue;
            }
        }

        // One pass, so a token inside an inserted value is not replaced again.
        return strtr($value, $map);
    }

    /**
     * HTML-escape every scalar and Stringable value, at any array depth.
     *
     * @param array<array-key,mixed> $values
     *
     * @return array<array-key,mixed>
     */
    private function escapeValues(array $values): array
    {
        $out = [];
        foreach ($values as $name => $value) {
            if (is_array($value)) {
                $out[$name] = $this->escapeValues($value);
                continue;
            }
            if (!is_scalar($value) && !($value instanceof \Stringable)) {
                $out[$name] = $value;
                continue;
            }
            try {
                $out[$name] = $this->escape((string) $value);
            } catch (\Throwable) {
                // A throwing Stringable::__toString() must never break
                // rendering. The value is dropped, not passed on: a second
                // conversion could succeed and would then go in unescaped.
                // Its token stays in the text.
            }
        }

        return $out;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Coerce a raw Smarty tag attribute to a scalar string, never raising a
     * PHP error/warning. Anything but null, a scalar or a Stringable is
     * treated as absent (empty string) rather than "Array to string
     * conversion" or an object-to-string Error — a malformed attribute value
     * must never break rendering.
     */
    private function toAttributeString(mixed $value): string
    {
        if (null === $value || is_scalar($value)) {
            return (string) $value;
        }

        try {
            // Called by name: static analysis does not see a cast as a throw point.
            return $value instanceof \Stringable ? $value->__toString() : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function deprecateFileParam(): void
    {
        if (!$this->debug) {
            return;
        }
        try {
            trigger_error('<{xoTrans file=…}> is deprecated; use domain=.', E_USER_DEPRECATED);
        } catch (\Throwable) {
            // A deprecation notice must never break rendering.
        }
    }

    private function fireMissingEvent(string $dirname, string $domain, string $key): void
    {
        if (!class_exists(\XoopsPreload::class)) {
            return;
        }
        try {
            $locale = '';
            if (class_exists(\Xmf\I18n\Translation::class) && \Xmf\I18n\Translation::isInitialized()) {
                $locale = \Xmf\I18n\Translation::getInstance()?->context()->tag ?? '';
            }
            \XoopsPreload::getInstance()->triggerEvent('core.i18n.missing', [[
                'dirname' => $dirname,
                'domain'  => $domain,
                'key'     => $key,
                'locale'  => $locale,
            ]]);
        } catch (\Throwable) {
            // Event dispatch must never break rendering.
        }
    }

    private static function logCollision(string $type, string $name): void
    {
        $message = "xoTrans {$type} collision: '{$name}' already registered.";

        try {
            if (isset($GLOBALS['xoopsLogger']) && is_object($GLOBALS['xoopsLogger']) && method_exists($GLOBALS['xoopsLogger'], 'addExtra')) {
                $GLOBALS['xoopsLogger']->addExtra('i18n', $message);

                return;
            }

            trigger_error($message, E_USER_NOTICE);
        } catch (\Throwable) {
            // Reporting must not stop the remaining plugins from registering
            // (an error handler may turn the notice into an exception).
        }
    }
}
