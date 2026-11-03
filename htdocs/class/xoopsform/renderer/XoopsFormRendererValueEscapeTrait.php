<?php
/**
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  XoopsForm
 * @package   XoopsFormRendererValueEscapeTrait
 * @author    Richard Griffith <richard@geekwright.com>
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 * @since     2.7.3
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

trait XoopsFormRendererValueEscapeTrait
{
    /**
     * Escape a form element value for HTML text/attribute context, idempotently.
     *
     * XOOPS callers historically hand elements an ALREADY-escaped value
     * (getVar($k, 'e'|'E'), MyTextSanitizer::htmlSpecialChars()), and roughly 95% of
     * core does. Escaping again here would double-escape them and rewrite stored text
     * on the next save. Decoding first makes this a no-op for those callers while
     * still neutralising the ones that pass raw user input -- which core itself does
     * in at least two places (kernel/menusitems.php fetches with 'n', and
     * include/comment_form.php re-displays raw $_POST on preview).
     *
     * Flags match kernel/object.php:478 so the round-trip is exact.
     *
     * @param mixed $value
     * @return string
     */
    protected function escapeElementValue($value): string
    {
        return htmlspecialchars(
            htmlspecialchars_decode((string) $value, ENT_QUOTES | ENT_HTML5),
            ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * Encode a value for use inside a JavaScript string literal in an inline
     * `<script>` block.
     *
     * Uses `json_encode` with `JSON_HEX_*` flags so the result cannot break out
     * of the surrounding `<script>` via `</script>`, cannot escape the string
     * via single/double quotes, and cannot introduce HTML entities via `&`.
     * The returned value INCLUDES its own surrounding quotes — do not wrap it
     * in `"..."` at the call site.
     *
     * @param string $value raw value to encode
     *
     * @return string JSON-encoded string literal including surrounding quotes
     */
    protected function esJs(string $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP
                | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $e) {
            trigger_error(static::class . '::esJs: ' . $e->getMessage(), E_USER_WARNING);

            return '""';
        }
    }

    /**
     * Build a safe JavaScript function call expression, with each argument
     * encoded through {@see esJs()}.
     *
     * Returns e.g. `xoopsCodeUrl("textarea_id", "Enter URL", "Enter title")`.
     * The result is ready for embedding in a single-quoted HTML attribute
     * (`onclick='...'`) because `esJs()` hex-escapes both quote characters.
     * For any other attribute use {@see jsHandler()}.
     *
     * Do NOT build `$fn` from user input — the function name is concatenated
     * verbatim. It is intended to be a hard-coded JavaScript function literal.
     *
     * @param string                 $fn   JavaScript function name (safe literal)
     * @param array<int, int|string> $args arguments to encode, in order
     *
     * @return string JavaScript expression of the form `fn("a","b",…)`
     */
    protected function buildJsCall(string $fn, array $args): string
    {
        $encoded = [];
        foreach ($args as $arg) {
            $encoded[] = $this->esJs((string) $arg);
        }

        return $fn . '(' . implode(', ', $encoded) . ')';
    }

    /**
     * Build an inline event-handler value: a JavaScript call with every argument
     * encoded as a string literal, then HTML-encoded for any quoted attribute.
     *
     * Pass RAW values (getName(false)), never HTML-encoded ones: the browser
     * decodes the attribute before it compiles the handler.
     *
     * @param string                 $fn     JavaScript function name (safe literal)
     * @param array<int, int|string> $args   raw arguments, in order
     * @param string                 $prefix safe literal JS placed before the call, e.g. 'return '
     *
     * @return string attribute value, without the surrounding quotes
     */
    protected function jsHandler(string $fn, array $args, string $prefix = ''): string
    {
        return htmlspecialchars($prefix . $this->buildJsCall($fn, $args) . ';', ENT_QUOTES, 'UTF-8');
    }
}
