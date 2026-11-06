<?php

declare(strict_types=1);

/**
 * Markdown transport and rendering shared by XOOPS editors and text fields.
 *
 * @category  Xoops
 * @package   Xoops\Editor
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * A Markdown document is stored in an existing text column between OPEN and
 * CLOSE markers, so modules need no schema change. MyTextSanitizer renders it
 * with Parsedown in safe mode instead of the BBCode pipeline.
 *
 * @category  Xoops
 * @package   Xoops\Editor
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsMarkdown
{
    public const OPEN = '[xoops:markdown="1"]';
    public const CLOSE = '[/xoops:markdown]';
    /** Blocks (quotes, list items) nested deeper than this render their source as text. */
    public const MAX_QUOTE_DEPTH = 16;
    /** preparePost() stops hashing and wrapping field text once this many bytes were processed. */
    public const MAX_PREPARED_BYTES = 4194304;

    /** @var array<string, string> submitted text => wrapped document, for previewSource() */
    private static array $previews = [];

    /**
     * Fingerprint of an editor value; browser form submissions normalize line endings.
     *
     * @param string $text editor value
     *
     * @return string sha256 hex digest
     */
    public static function fingerprint(string $text): string
    {
        return hash('sha256', str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * The wrapped document for an unsaved preview. Previews must not add format
     * markers to the submitted source, so preparePost() records them here.
     *
     * @param string $text submitted field value
     *
     * @return string the wrapped document, or $text when it is not a Markdown field
     */
    public static function previewSource(string $text): string
    {
        return self::$previews[$text] ?? $text;
    }

    /**
     * Editable source of a stored document.
     *
     * @param string $text stored value, raw or escaped by getVar('e')
     *
     * @return string|null the Markdown source, or null for an ordinary legacy text field
     */
    public static function source(string $text): ?string
    {
        // A quoted marker distinguishes getVar('e') from raw source. Decode
        // only that form, so intentional entities inside raw code stay intact.
        if (str_starts_with($text, '[xoops:markdown=&quot;1&quot;]')) {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (!str_starts_with($text, self::OPEN) || !str_ends_with($text, self::CLOSE)) {
            return null;
        }
        $source = substr($text, strlen(self::OPEN), -strlen(self::CLOSE));
        // Adjacent stored fields are separate documents, not one outer wrapper.
        // A genuine source-level marker is escaped by wrap().
        if (str_contains($source, '[xoops:markdown=') || str_contains($source, self::CLOSE)) {
            return null;
        }
        $source = preg_replace('/\A\r?\n|\r?\n\z/', '', $source);
        return strtr($source, ['%3C' => '<', '%3E' => '>', '%26' => '&', '%5B' => '[', '%25' => '%']);
    }

    /**
     * Wrap Markdown source for storage. Source stays searchable in existing
     * columns; empty input is never tagged.
     *
     * @param string $text Markdown source (an already wrapped value is re-wrapped from its source)
     *
     * @return string the stored document
     */
    public static function wrap(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }
        $source = self::source($text) ?? $text;
        // Legacy getString()/strip_tags() readers must not erase HTML examples
        // or decode entities in code. Only protect those bytes and our reserved
        // delimiters; ordinary words remain searchable in existing columns.
        $source = strtr($source, ['%' => '%25', '<' => '%3C', '>' => '%3E', '&' => '%26', '[xoops:' => '%5Bxoops:', '[/xoops:' => '%5B/xoops:']);
        return self::OPEN . "\n" . $source . "\n" . self::CLOSE;
    }

    /**
     * Source for EasyMDE, including a legacy forum's quoted document.
     *
     * @param string $text stored value
     *
     * @return string editable text
     */
    public static function editorSource(string $text): string
    {
        $source = self::source($text);
        if ($source !== null) {
            return $source;
        }
        if (str_contains($text, '[xoops:markdown=') && preg_match('/\A\[quote\](.*)\[\/quote\]\z/s', $text, $quote)) {
            $body = preg_replace_callback(
                '/\[xoops:markdown=(?:"1"|&quot;1&quot;)\]\r?\n.*?\r?\n\[\/xoops:markdown\]/s',
                static fn(array $match): string => self::source($match[0]) ?? $match[0],
                $quote[1],
            );
            if ($body !== null) {
                return preg_replace('/^/m', '> ', trim($body, "\r\n"));
            }
        }
        return $text;
    }

    /**
     * Tag editor fields before module handlers read POST. Mirror only matching
     * REQUEST values for legacy readers, preserving request-source precedence.
     * This does not authorize saves or change permission, CSRF, or HTML flags.
     *
     * @param array<string, mixed> $post    POST input, modified in place
     * @param array<string, mixed> $request REQUEST input, modified in place
     *
     * @return void
     */
    public static function preparePost(array &$post, array &$request): void
    {
        self::$previews = [];
        $aliases = [];
        $fields = $post['_xoops_markdown'] ?? [];
        if (!is_array($fields)) {
            return;
        }
        // Each field path is processed once, and the text processed across all
        // fields is bounded: this runs at bootstrap, before any authorization.
        $budget = self::MAX_PREPARED_BYTES;
        foreach (array_unique(array_filter($fields, 'is_string')) as $field) {
            if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\[[A-Za-z0-9_]+\])*\z/', $field)) {
                continue;
            }
            preg_match_all('/[^\[\]]+/', $field, $parts);
            $path = $parts[0];
            $root = $path[0];
            if ($root === '_xoops_markdown' || !array_key_exists($root, $post)) {
                continue;
            }
            $original = $post[$root];
            $value = &$post;
            foreach ($path as $key) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    unset($value);
                    continue 2;
                }
                $value = &$value[$key];
            }
            if (is_string($value)) {
                $length = strlen($value);
                if ($length > $budget) {
                    unset($value);
                    continue;
                }
                $budget -= $length;
                $states = $post['_xoops_markdown_state'] ?? null;
                $state = is_array($states) ? $states[hash('sha256', $field)] ?? null : null;
                if (!is_array($state) || !is_string($state['initial'] ?? null)) {
                    unset($value);
                    continue;
                }
                $marked = ($state['marked'] ?? null) === '1';
                $changed = self::fingerprint($value) !== $state['initial'];
                if ($marked || $changed) {
                    self::$previews[$value] = self::wrap($value);
                    $aliases[trim($value)][$value] = true;
                }
                // Restore an existing marker on round trips; introduce a NEW
                // marker only after an edit and an explicit Save submission.
                if ($marked || ($changed && ($post['_xoops_markdown_save'] ?? null) === '1')) {
                    $value = self::wrap($value);
                }
                if (array_key_exists($root, $request) && $request[$root] === $original) {
                    $request[$root] = $post[$root];
                }
            }
            unset($value);
        }
        // Also under the trimmed text: Request::getString() trims, and a module
        // may preview with that value. A field's exact text wins; a trimmed text
        // shared by different fields previews as itself.
        foreach ($aliases as $trimmed => $originals) {
            $trimmed = (string) $trimmed;
            if (!isset(self::$previews[$trimmed])) {
                self::$previews[$trimmed] = 1 === count($originals)
                    ? self::$previews[(string) array_key_first($originals)]
                    : self::wrap($trimmed);
            }
        }
    }

    /**
     * Expand tabs to Parsedown's 4-column stops in one pass.
     *
     * Parsedown rebuilds a line once per tab it holds, which is quadratic in
     * the number of tabs; after this pass it finds none. Line endings are
     * normalised first, as Parsedown does, so columns restart where it restarts.
     *
     * @param string $source Markdown source
     *
     * @return string source with the same meaning
     */
    private static function expandTabs(string $source): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $source));
        foreach ($lines as &$line) {
            if (!str_contains($line, "\t")) {
                continue;
            }
            $expanded = '';
            $column   = 0;
            foreach (explode("\t", $line) as $i => $segment) {
                if ($i > 0) {
                    $pad = 4 - $column % 4;
                    $expanded .= str_repeat(' ', $pad);
                    $column += $pad;
                }
                $expanded .= $segment;
                $column += mb_strlen($segment, 'UTF-8');
            }
            $line = $expanded;
        }
        unset($line);

        return implode("\n", $lines);
    }

    /**
     * Parsedown with a nesting cap. A container (blockquote, list item) parses
     * its content while element() renders it, one level of recursion per
     * container with no limit of its own, in 1.7 and 1.8 alike; a line-based
     * cap cannot hold because lazy continuation lets each line nest inside the
     * last. A container below MAX_QUOTE_DEPTH renders its source as text.
     *
     * @return \Parsedown safe-mode parser
     */
    private static function parser(): \Parsedown
    {
        $parser = new class () extends \Parsedown {
            private int $depth = 0;

            protected function element(array $Element)
            {
                if (!in_array($Element['name'] ?? null, ['blockquote', 'li'], true)) {
                    return parent::element($Element);
                }
                if ($this->depth >= XoopsMarkdown::MAX_QUOTE_DEPTH) {
                    // 1.8 keeps the source lines under handler.argument, 1.7 under text.
                    $lines = $Element['handler']['argument'] ?? $Element['text'] ?? [];

                    return '<p>' . self::escape(implode("\n", (array) $lines)) . '</p>';
                }
                ++$this->depth;
                try {
                    return parent::element($Element);
                } finally {
                    --$this->depth;
                }
            }
        };
        $parser->setSafeMode(true);

        return $parser;
    }

    /**
     * Render untrusted source independently of legacy HTML/BBCode flags.
     *
     * @param string $source Markdown source
     * @param bool   $images false renders images as their alternative text (doimage=0)
     *
     * @return string safe HTML (Parsedown safe mode)
     */
    public static function render(string $source, bool $images = true): string
    {
        if (!class_exists(\Parsedown::class) && is_readable(XOOPS_TRUST_PATH . '/vendor/autoload.php')) {
            require_once XOOPS_TRUST_PATH . '/vendor/autoload.php';
        }
        if (!class_exists(\Parsedown::class)) {
            // A site upgraded without refreshing xoops_lib/vendor has no Parsedown yet.
            trigger_error('Parsedown is not installed; Markdown is shown as plain text', E_USER_WARNING);

            return '<pre>' . htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        }
        $html = self::parser()->text(self::expandTabs($source));
        if (!$images) {
            // Attributes in Parsedown's generated img elements are quoted and
            // escaped. Keep alternative text without bypassing doimage=0.
            $html = preg_replace_callback('/<img\b[^>]*\balt="([^"]*)"[^>]*>/', static fn(array $match): string => $match[1], $html);
        }
        return $html;
    }

    /**
     * Replace marked documents inside legacy replies with placeholders until the
     * BBCode pipeline completes; restore() swaps the rendered HTML back in.
     *
     * @param string $text   sanitizer input, modified in place
     * @param bool   $images false renders images as their alternative text
     *
     * @return array<string, string> placeholder => rendered HTML
     *
     * @throws \Exception when random_bytes() cannot gather entropy for the placeholder prefix
     */
    public static function protect(string &$text, bool $images = true): array
    {
        $rendered = [];
        if (!str_contains($text, '[xoops:markdown=')) {
            return $rendered;
        }
        $prefix = 'XOOPSMARKDOWN' . bin2hex(random_bytes(16));
        $text = preg_replace_callback(
            '/(\[code[^\]]*\].*?\[\/code\])|(\[xoops:markdown=(?:"1"|&quot;1&quot;)\]\r?\n.*?\r?\n\[\/xoops:markdown\])/s',
            static function (array $match) use (&$rendered, $prefix, $images): string {
                if ($match[1] !== '') {
                    return $match[0];
                }
                $source = self::source($match[2]);
                if ($source === null) {
                    // Nested or malformed markers stay ordinary escaped text.
                    return $match[0];
                }
                $token = $prefix . count($rendered) . 'END';
                $rendered[$token] = self::render($source, $images);
                return $token;
            },
            $text,
        ) ?? $text;
        return $rendered;
    }

    /**
     * Swap protected documents back in, but only in text content. BBCode may
     * have copied a placeholder into a generated attribute ([url=...]); rendered
     * HTML there would break out of the attribute, so drop it instead.
     *
     * @param string                $text     sanitizer output holding placeholders
     * @param array<string, string> $rendered placeholder => rendered HTML, from protect()
     *
     * @return string
     */
    public static function restore(string $text, array $rendered): string
    {
        if ($rendered === []) {
            // An empty alternation would match at every position.
            return $text;
        }
        $tokens = implode('|', array_map('preg_quote', array_keys($rendered)));
        return preg_replace_callback(
            // Quote-aware: a '>' inside a quoted attribute does not end the tag.
            '/<(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>|' . $tokens . '/',
            static fn(array $match): string => $match[0][0] === '<'
                ? str_replace(array_keys($rendered), '', $match[0])
                : $rendered[$match[0]],
            $text,
        ) ?? $text;
    }
}
