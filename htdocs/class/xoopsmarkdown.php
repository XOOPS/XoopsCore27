<?php
declare(strict_types=1);

/**
 * Markdown transport and rendering shared by XOOPS editors and text fields.
 * @copyright Copyright (c) XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 or later
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

final class XoopsMarkdown
{
    public const OPEN = '[xoops:markdown="1"]';
    public const CLOSE = '[/xoops:markdown]';

    private static array $previews = [];

    /** Browser form submissions normalize line endings. */
    public static function fingerprint(string $text): string
    {
        return hash('sha256', str_replace(["\r\n", "\r"], "\n", $text));
    }

    /** Unsaved previews must not add format markers to the submitted source. */
    public static function previewSource(string $text): string
    {
        return self::$previews[$text] ?? $text;
    }

    /** Return editable source, or null for an ordinary legacy text field. */
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

    /** Keep source searchable in existing columns; never tag empty input. */
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

    /** Restore source for EasyMDE, including a legacy forum's quoted document. */
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
     */
    public static function preparePost(array &$post, array &$request): void
    {
        self::$previews = [];
        $fields = $post['_xoops_markdown'] ?? [];
        if (!is_array($fields)) {
            return;
        }
        foreach ($fields as $field) {
            if (!is_string($field) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\[[A-Za-z0-9_]+\])*\z/', $field)) {
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
                $state = $post['_xoops_markdown_state'][hash('sha256', $field)] ?? null;
                if (!is_array($state) || !is_string($state['initial'] ?? null)) {
                    unset($value);
                    continue;
                }
                $marked = ($state['marked'] ?? null) === '1';
                $changed = self::fingerprint($value) !== $state['initial'];
                if ($marked || $changed) {
                    self::$previews[$value] = self::wrap($value);
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
    }

    /** Render untrusted source independently of legacy HTML/BBCode flags. */
    public static function render(string $source, bool $images = true): string
    {
        if (!class_exists(\Parsedown::class)) {
            require_once XOOPS_TRUST_PATH . '/vendor/autoload.php';
        }
        $parser = new \Parsedown();
        $parser->setSafeMode(true);
        $html = $parser->text($source);
        if (!$images) {
            // Attributes in Parsedown's generated img elements are quoted and
            // escaped. Keep alternative text without bypassing doimage=0.
            $html = preg_replace_callback('/<img\b[^>]*\balt="([^"]*)"[^>]*>/', static fn(array $match): string => $match[1], $html);
        }
        return $html;
    }

    /** Protect marked documents inside legacy replies until BBCode completes. */
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
        $tokens = implode('|', array_map('preg_quote', array_keys($rendered)));
        return preg_replace_callback(
            '/<[^>]*>|' . $tokens . '/',
            static fn(array $match): string => $match[0][0] === '<'
                ? str_replace(array_keys($rendered), '', $match[0])
                : $rendered[$match[0]],
            $text,
        ) ?? $text;
    }
}
