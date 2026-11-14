<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

use Xoops\Helpers\Utility\HtmlBuilder;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.
*/

/**
 * Reusable "share this page" renderer — a single implementation every module can
 * call instead of copy-pasting share markup. Pure (no DB, no XOOPS singletons),
 * so it is trivially unit-testable; all user-supplied text is escaped.
 *
 * Usage:
 *   echo SocialBookmarks::render($absoluteUrl, $pageTitle);
 *   echo SocialBookmarks::render($url, $title, ['x', 'facebook', 'email', 'copy']);
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.3.0
 */
final class SocialBookmarks
{
    /** Default set of networks, in display order. */
    public const array DEFAULT_NETWORKS = ['x', 'facebook', 'linkedin', 'whatsapp', 'email', 'copy'];

    /**
     * Build the share-bar HTML.
     *
     * @param string       $url      absolute URL to share
     * @param string       $title    page title / share text
     * @param list<string> $networks subset/order of self::DEFAULT_NETWORKS
     */
    public static function render(string $url, string $title, array $networks = []): string
    {
        $url = \trim($url);
        if ('' === $url) {
            return '';
        }
        $networks = [] === $networks ? self::DEFAULT_NETWORKS : $networks;

        $u = \rawurlencode($url);
        $t = \rawurlencode($title);

        // [label, href, FontAwesome-4-safe icon, brand colour]
        $catalog = [
            'x'        => ['X / Twitter', 'https://twitter.com/intent/tweet?url=' . $u . '&text=' . $t, 'fa-twitter',  '#1da1f2'],
            'facebook' => ['Facebook',    'https://www.facebook.com/sharer/sharer.php?u=' . $u,         'fa-facebook', '#1877f2'],
            'linkedin' => ['LinkedIn',    'https://www.linkedin.com/sharing/share-offsite/?url=' . $u,  'fa-linkedin', '#0a66c2'],
            'whatsapp' => ['WhatsApp',    'https://api.whatsapp.com/send?text=' . $t . '%20' . $u,       'fa-whatsapp', '#25d366'],
            'email'    => ['Email',       'mailto:?subject=' . $t . '&body=' . $u,                       'fa-envelope', '#ea4335'],
        ];

        $esc = static fn(string $s): string => HtmlBuilder::escape($s);

        // Pronounced circular brand buttons with white glyphs.
        $circle = 'display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;'
            . 'border-radius:50%;color:#fff;text-decoration:none;transition:opacity .15s';
        $hover  = 'onmouseover="this.style.opacity=0.85" onmouseout="this.style.opacity=1"';

        $html = '<span class="mtools-social-bookmarks" style="display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap">';
        foreach ($networks as $key) {
            if ('copy' === $key) {
                $html .= '<a href="#" class="mtools-share-copy" data-url="' . $esc($url) . '" title="Copy link" '
                    . 'style="' . $circle . ';background:#0d6efd" ' . $hover . ' '
                    . 'onclick="if(navigator.clipboard){navigator.clipboard.writeText(this.getAttribute(\'data-url\'));}return false;">'
                    . '<i class="fa fa-link" style="color:#fff" aria-hidden="true"></i></a>';
                continue;
            }
            if (!isset($catalog[$key])) {
                continue;
            }
            [$label, $href, $icon, $color] = $catalog[$key];
            $html .= '<a href="' . $esc($href) . '" target="_blank" rel="noopener noreferrer" title="' . $esc($label) . '" '
                . 'style="' . $circle . ';background:' . $color . '" ' . $hover . '>'
                . '<i class="fa ' . $esc($icon) . '" style="color:#fff" aria-hidden="true"></i></a>';
        }
        $html .= '</span>';

        return $html;
    }
}
