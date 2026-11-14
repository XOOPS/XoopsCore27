<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

use Xoops\Helpers\Utility\HtmlBuilder;

/**
 * @category     Module
 * @package      mtools
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @copyright    https://xoops.org 2000-2026 &copy; XOOPS Project
 * @author       ZySpec <zyspec@yahoo.com>
 * @author       Mamba <mambax7@gmail.com>
 */

/**
 * Pure text helpers extracted from {@see SysUtility}.
 *
 * No request reads, no output, no globals, no database — the strongest XMF candidate
 * in the Common tier. {@see SysUtility::truncateHtml()} forwards here for backward
 * compatibility, so existing `$utility::truncateHtml(...)` calls keep working.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
final class Text implements TextInterface
{
    // ponytail: past this many tags an excerpt falls back to plain text; raise it if real content needs more
    private const int MAX_HTML_TAGS = 2000;
    private const string ENCODING = 'UTF-8';
    private const array BLOCK_TAGS = ['br', 'hr', 'p', 'div', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'tr', 'td', 'th', 'blockquote', 'ul', 'ol', 'table', 'section', 'article', 'header', 'footer'];
    private const array VOID_TAGS = ['img', 'br', 'input', 'hr', 'area', 'base', 'basefont', 'col', 'frame', 'isindex', 'link', 'meta', 'param', 'embed', 'source', 'track', 'wbr'];

    /**
     * truncateHtml can truncate a string up to a number of characters while preserving whole words and HTML tags
     * www.gsdesign.ro/blog/cut-html-string-without-breaking-the-tags
     * www.cakephp.org
     *
     * @param string $text         String to truncate.
     * @param int    $length       Length of returned string, including ellipsis.
     *                            Nonpositive limits return ''; an oversized ending is clipped to fit.
     * @param string $ending       Ending to be appended to the trimmed string.
     * @param bool   $exact        If false, $text will not be cut mid-word
     * @param bool   $considerHtml If true, HTML tags would be handled correctly
     *
     * @return string Trimmed string.
     */
    public static function truncateHtml(
        string $text,
        int $length = 100,
        string $ending = '...',
        bool $exact = false,
        bool $considerHtml = true
    ): string {
        $openTags = [];
        $entityPattern = '/&(?:[a-z][a-z0-9]*|#[0-9]+|#x[0-9a-f]+);/i';
        if ($length <= 0) {
            return '';
        }
        $plainText = $considerHtml
            ? (string) preg_replace($entityPattern, ' ', strip_tags($text))
            : $text;
        if (mb_strlen($plainText) <= $length) {
            return $text;
        }
        if (mb_strlen($ending) >= $length) {
            return mb_substr($ending, 0, $length);
        }
        // Tags cost no visible characters, so $length does not bound the tag walk, and closing
        // tags that miss the top of the stack search it. Past the budget, excerpt plain text.
        if ($considerHtml && \substr_count($text, '<') > self::MAX_HTML_TAGS) {
            // Block boundaries become spaces so paragraphs do not run together; entities are
            // decoded so each counts as one character and is never cut, then the excerpt is re-escaped.
            // Possessive, so a long attribute cannot exhaust the PCRE stack; if PCRE fails anyway,
            // the tags are still stripped, only without the block spacing. An unquoted "<" ends the
            // candidate, so a failed attempt never rescans the next one ("<p <p <p ..." was quadratic).
            $spaced = \preg_replace_callback(
                '#</?([a-z][a-z0-9-]*+)(?:[^<>"\']++|"[^"]*+"|\'[^\']*+\')*+>#i',
                static fn (array $tag): string => \in_array(\strtolower($tag[1]), self::BLOCK_TAGS, true) ? ' ' : $tag[0],
                $text
            );
            $plain = \strip_tags($spaced ?? $text);
            // Scrubbed first: a /u regex on malformed UTF-8 returns null and the excerpt would be empty.
            $plain = \mb_scrub(\html_entity_decode($plain, \ENT_QUOTES | \ENT_HTML5 | \ENT_SUBSTITUTE, self::ENCODING), self::ENCODING);
            $plain = \trim((string) \preg_replace('/\s+/u', ' ', $plain));

            if (mb_strlen($plain) <= $length) {
                return HtmlBuilder::escape($plain);
            }
            // The text is escaped; $ending is caller markup (e.g. "&hellip;") appended raw, as on the HTML path.
            $excerpt = self::truncateHtml($plain, $length - mb_strlen($ending), '', $exact, false);

            return HtmlBuilder::escape($excerpt) . $ending;
        }
        if ($considerHtml) {
            $total_length = mb_strlen($ending);
            $openTags     = [];
            $truncate     = '';
            foreach (self::tokenize($text) as $lineMatchings) {
                // if there is any html-tag in this line, handle it and add it (uncounted) to the output
                if (!empty($lineMatchings[1])) {
                    // if it's an "empty element" with or without xhtml-conform closing slash
                    if (self::isEmptyElement($lineMatchings[1])) {
                        // do nothing
                        // if tag is a closing tag
                    } elseif (\preg_match('/^<\s*\/(\S+?)\s*>$/s', $lineMatchings[1], $tag_matchings)) {
                        // close the newest open tag of that name (well-nested HTML: the top of the stack)
                        $closing = \mb_strtolower($tag_matchings[1]);
                        if (\end($openTags) === $closing) {
                            \array_pop($openTags);
                        } elseif (false !== ($pos = \array_search($closing, \array_reverse($openTags, true), true))) {
                            unset($openTags[$pos]);
                        }
                        // if tag is an opening tag
                    } elseif (\preg_match('/^<\s*([^\s>!]+).*?' . '>$/s', $lineMatchings[1], $tag_matchings)) {
                        $openTags[] = \mb_strtolower($tag_matchings[1]);
                    }
                    // add html-tag to $truncate'd text
                    $truncate .= $lineMatchings[1];
                }
                // calculate the length of the plain text part of the line; handle entities as one character
                $content_length = mb_strlen((string) \preg_replace($entityPattern, ' ', $lineMatchings[2]));
                if ($total_length + $content_length > $length) {
                    // the number of characters which are left
                    $left            = $length - $total_length;
                    $entities_length = 0;
                    // search for html entities
                    if (\preg_match_all($entityPattern, $lineMatchings[2], $entities, \PREG_OFFSET_CAPTURE)) {
                        // calculate the real length of all entities in the legal range;
                        // each span between entities is counted once, not the whole prefix again
                        $byteOffset      = 0;
                        $characterOffset = 0;
                        foreach ($entities[0] as $entity) {
                            $characterOffset += mb_strlen(substr($lineMatchings[2], $byteOffset, $entity[1] - $byteOffset));
                            $byteOffset       = $entity[1];
                            if ($left >= $characterOffset + 1 - $entities_length) {
                                $left--;
                                $entities_length += mb_strlen($entity[0]);
                            } else {
                                // no more characters left
                                break;
                            }
                        }
                    }
                    $truncate .= mb_substr($lineMatchings[2], 0, $left + $entities_length);
                    // maximum lenght is reached, so get off the loop
                    break;
                }
                $truncate     .= $lineMatchings[2];
                $total_length += $content_length;

                // if the maximum length is reached, get off the loop
                if ($total_length >= $length) {
                    break;
                }
            }
        } else {
            $truncate = mb_substr($text, 0, $length - mb_strlen($ending));
        }
        // if the words shouldn't be cut in the middle...
        if (!$exact) {
            // ...search the last occurance of a space...
            $spacepos = mb_strrpos($truncate, ' ');
            if (false !== $spacepos) {
                // ...and cut the text in this position
                $truncate = mb_substr($truncate, 0, $spacepos);
            }
        }
        // add the defined ending to the text
        $truncate .= $ending;
        if ($considerHtml) {
            // close all unclosed html-tags, innermost first
            foreach (\array_reverse($openTags) as $tag) {
                $truncate .= '</' . $tag . '>';
            }
        }

        return $truncate;
    }

    /**
     * Splits HTML into [1 => tag, 2 => text] pieces as `/(<.+?>)?([^<>]*)/s` did, in one forward pass
     * that stops when the caller stops. The regex rescanned the rest of the text for every `<` with no
     * later `>` (a 900 KB tail took 33 s), and past PCRE's backtrack limit it cut the excerpt short.
     * A stray `<` or `>` is dropped, as before.
     *
     * @return \Generator<int, array{1: string, 2: string}>
     */
    private static function tokenize(string $html): \Generator
    {
        $size      = \strlen($html);
        $lastClose = \strrpos($html, '>');
        $offset    = 0;
        while ($offset < $size) {
            $tag = '';
            // A tag needs one character before its ">"; past the last ">" nothing can close.
            if ('<' === $html[$offset] && false !== $lastClose && $offset + 2 <= $lastClose) {
                $end    = (int) \strpos($html, '>', $offset + 2);
                $tag    = \substr($html, $offset, $end - $offset + 1);
                $offset = $end + 1;
            }
            $textLength = \strcspn($html, '<>', $offset);
            if ('' === $tag && 0 === $textLength) {
                ++$offset;
                continue;
            }
            yield [1 => $tag, 2 => \substr($html, $offset, $textLength)];
            $offset += $textLength;
        }
    }

    /**
     * A self-closing tag (`<x ... />`) or a void element (`<br>`, `<img src=...>`).
     * String checks, not a regex: overlapping repetitions made each long tag cost quadratic time.
     */
    private static function isEmptyElement(string $tag): bool
    {
        $whitespace = " \t\n\r\v\f";
        $inner = \trim(\substr($tag, 1, -1), $whitespace);
        if (\strlen($inner) > 1 && \str_ends_with($inner, '/')) {
            return true;
        }
        $nameLength = \strspn(\strtolower($inner), 'abcdefghijklmnopqrstuvwxyz');
        $next = \substr($inner, $nameLength, 1);

        return \in_array(\strtolower(\substr($inner, 0, $nameLength)), self::VOID_TAGS, true)
            && ('' === $next || \str_contains($whitespace, $next));
    }
}
