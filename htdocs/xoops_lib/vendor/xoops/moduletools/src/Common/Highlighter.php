<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

use Xoops\Helpers\Utility\HtmlBuilder;

/** Highlights user-supplied search terms in text while escaping the input. */
final readonly class Highlighter
{
    /** @var list<string> */
    private array $terms;

    public function __construct(string $keywords, bool $singleWords = true, private string $cssClass = 'moduletools-highlight')
    {
        $terms = $singleWords ? preg_split('/\s+/u', trim($keywords)) : [trim($keywords)];
        $terms = array_values(array_filter(array_unique(array_map(strval(...), $terms ?: [])), static fn (string $term): bool => '' !== $term));
        // Longest first, so "abc" is marked whole instead of "ab" winning the alternation.
        usort($terms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $this->terms = $terms;
    }

    public function highlight(string $text): string
    {
        if ([] === $this->terms) {
            return self::escape($text);
        }
        // Match on the raw text and escape each piece afterwards: a term can never match
        // inside an entity the escaping produced ("amp" must not split "&amp;").
        $pattern = '/(' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $this->terms)) . ')/iu';
        $pieces  = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (false === $pieces) {
            return self::escape($text);
        }
        $open = '<mark class="' . HtmlBuilder::escape($this->cssClass) . '">';
        $out  = '';
        foreach ($pieces as $index => $piece) {
            $out .= 1 === $index % 2 ? $open . self::escape($piece) . '</mark>' : self::escape($piece);
        }

        return $out;
    }

    private static function escape(string $text): string
    {
        return HtmlBuilder::escape($text);
    }
}
