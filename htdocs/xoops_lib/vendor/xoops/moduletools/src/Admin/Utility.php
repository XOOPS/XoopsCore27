<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Admin;

use Xoops\Helpers\Utility\HtmlBuilder;

/**
 * Transitional admin rendering helpers shared by converted Smart modules.
 *
 * The misspelled legacy methods remain as echoing adapters because existing
 * admin controllers call them for their side effect. New code can use the
 * correctly spelled pure rendering methods and decide where to emit HTML.
 *
 * @api Stable Common-tier API
 */
final class Utility
{
    public static function openCollapsible(string $id, string $title, string $description = ''): string
    {
        $safeId          = self::normalizeId($id);
        $escapedId       = self::escape($safeId);
        $escapedTitle    = self::escape($title);
        $escapedDescription = self::escape($description);

        $html = '<h3 class="xoops-moduletools-collapsible-title">'
            . '<button type="button" aria-expanded="true" aria-controls="' . $escapedId . '" '
            . 'onclick="togglecollapse(\'' . $escapedId . '\'); return false;">'
            . '<span aria-hidden="true">&#8722;</span> ' . $escapedTitle . '</button></h3>'
            . "\n<div id=\"" . $escapedId . '" class="xoops-moduletools-collapsible">';

        if ('' !== $description) {
            $html .= "\n<p class=\"xoops-moduletools-collapsible-description\">"
                . $escapedDescription . '</p>';
        }

        return $html . "\n";
    }

    public static function closeCollapsible(): string
    {
        return "</div>\n";
    }

    /**
     * @deprecated Use {@see self::openCollapsible()} and emit its return value.
     */
    public static function getCollapsableBar(string $id = '', string $title = '', string $dsc = ''): void
    {
        echo self::openCollapsible($id, $title, $dsc);
    }

    /**
     * @deprecated Use {@see self::closeCollapsible()} and emit its return value.
     */
    public static function closeCollapsable(string $name = ''): void
    {
        echo self::closeCollapsible();
    }

    private static function normalizeId(string $id): string
    {
        $normalized = (string) preg_replace('/[^A-Za-z0-9_-]/', '_', $id);

        return '' !== $normalized ? $normalized : 'moduletools-section';
    }

    private static function escape(string $value): string
    {
        return HtmlBuilder::escape($value);
    }
}
