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
 * Self-contained pagination helper for mtools consumers.
 *
 * Re-implements the pagination logic previously borrowed from the TadTools
 * `PageBar` class so that mtools has NO runtime dependency on the tadtools
 * module. The idea is ported; the dependency is not.
 *
 * It returns Bootstrap-friendly `<li>` fragments (left / center / right) plus a
 * `LIMIT` clause, mirroring the array shape the legacy `Utility::getPageBar()`
 * consumed, so it is a drop-in replacement. Markup uses HTML entities, not
 * images, so it needs no asset files.
 *
 * @category  Module
 * @package   mtools
 * @author    Mamba <mambax7@gmail.com>
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://github.com/mambax7/mtools
 *
 * @deprecated Since the xoops/smartyextensions adoption: render pagination in
 *             templates with the `render_pagination` Smarty plugin
 *             (NavigationExtension, Bootstrap 5) instead. Kept for BC for any
 *             PHP-side caller that still needs limit/offset data.
 *
 *             NOT a promotion-ready public API as written: it reads
 *             $_SERVER['PHP_SELF'], builds hrefs by hand, returns raw SQL LIMIT
 *             fragments, and accepts an arbitrary $orderSql fragment (caller-trust
 *             only — never pass user input to it). Before any promotion to
 *             xoops/helpers it must split page-math from rendering/SQL, use
 *             http_build_query, escape final hrefs, and drop the free-form ORDER BY.
 *
 *             The page-math half of that split is DONE: the pure, side-effect-free
 *             {@see PaginationState} (the promotion candidate) now does the counting;
 *             this class is the legacy renderer that delegates to it.
 */
class Paginator
{
    /** Rows per page. */
    public int $limit = 20;

    /** Page links shown per "page band". */
    public int $pageLimit = 10;

    /** Target page (script) the links point to. */
    public string $toPage = '';

    /** Extra string appended to each generated URL (e.g. an anchor). */
    public string $urlOther = '';

    /** Query-string parameter that carries the current page number. */
    public string $urlPage = 'g2p';

    /** Current page number (1-based). */
    public int $current = 1;

    /** Total number of pages. */
    public int $pTotal = 0;

    /** Current page band. */
    public int $pCurrent = 1;

    /** Preserved query string (without the page parameter). */
    public string $queryStr = '';

    /** '?' or '&' glue for appending the page parameter. */
    public string $glue = '?';

    /**
     * @param int    $total    total number of rows in the unpaginated result
     * @param string $orderSql optional ORDER BY fragment placed before the LIMIT clause
     */
    public function __construct(
        public int $total,
        int $limit = 20,
        int $pageLimit = 10,
        public string $orderSql = ''
    ) {
        $this->limit     = $limit > 0 ? $limit : 20;
        $this->pageLimit = $pageLimit > 0 ? $pageLimit : 10;
        $this->toPage    = $_SERVER['PHP_SELF'] ?? '';
    }

    public function setToPage(string $page = ''): void
    {
        $this->toPage = $page;
    }

    public function setUrlOther(string $other = ''): void
    {
        $this->urlOther = $other;
    }

    /**
     * Re-read state from the request and recompute derived counters.
     */
    public function init(): void
    {
        $this->queryStr = $this->processQuery([$this->urlPage]);
        $this->glue     = ('' === $this->queryStr) ? '?' : '&';

        $requested = isset($_GET[$this->urlPage]) ? (int) $_GET[$this->urlPage] : 1;

        // Delegate the page math to the pure, side-effect-free PaginationState; this class
        // keeps only the request-reading (above) and the Bootstrap markup (below).
        $state          = new PaginationState($this->total, $this->limit, $requested, $this->pageLimit);
        $this->pTotal   = $state->pageCount();
        $this->current  = $state->currentPage();
        $this->pCurrent = $state->band();
    }

    /**
     * Rebuild the current query string minus the page parameter, URL-encoding each
     * key/value (RFC 3986) so an embedded "&" or "#" survives the round trip. The
     * result is not HTML-escaped; {@see pageHref()} escapes the whole href at emission.
     *
     * @param list<string> $usedQuery parameter names to drop
     */
    public function processQuery(array $usedQuery): string
    {
        \parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $queryVars);
        $filtered = \array_diff_key($queryVars, \array_flip($usedQuery));

        return [] === $filtered ? '' : '?' . \http_build_query($filtered, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * The `... LIMIT start, length` clause for the current page.
     */
    public function sqlQuery(): string
    {
        $rowStart = ($this->current - 1) * $this->limit;
        if ($rowStart < 0) {
            $rowStart = 0;
        }
        $limit = '' !== $this->orderSql ? " {$this->orderSql} LIMIT {$rowStart}, {$this->limit}" : " LIMIT {$rowStart}, {$this->limit}";

        return $limit;
    }

    /**
     * Build a Bootstrap pagination bar.
     *
     * @return array{center:string,left:string,right:string,current:int,total:int,start:int,end:int,sql:string}
     */
    public function makeBootStrapBar(string $urlPage = 'g2p', string $bootstrap = '4'): array
    {
        if ('' !== $urlPage) {
            $this->urlPage = $urlPage;
        }
        $this->init();

        $back    = self::label('_MA_MTOOLS_BACK_PAGE', 'Previous');
        $next    = self::label('_MA_MTOOLS_NEXT_PAGE', 'Next');
        $firstLb = self::label('_MA_MTOOLS_FIRST_PAGE', 'First');
        $lastLb  = self::label('_MA_MTOOLS_LAST_PAGE', 'Last');
        $curLb   = self::label('_MA_MTOOLS_CURRENT_PAGE', 'current page');

        $loadtime = $this->urlOther;
        $start    = ($this->pCurrent - 1) * $this->pageLimit + 1;
        $end      = (int) \min($this->pTotal, $this->pCurrent * $this->pageLimit);

        $center = '';
        for ($i = $start; $i <= $end; $i++) {
            $active      = $i === $this->current ? ' active' : '';
            $ariaCurrent = $i === $this->current ? ' aria-current="page"' : '';
            $srOnly      = $i === $this->current ? '<span class="sr-only visually-hidden"> (' . $curLb . ')</span>' : '';
            $center .= \sprintf(
                '<li class="page-item%s"%s><a class="page-link" href="%s">%d%s</a></li>',
                $active,
                $ariaCurrent,
                $this->pageHref($i, $loadtime),
                $i,
                $srOnly
            );
        }

        $first = $this->current <= 1
            ? $this->disabledItem('&laquo;', $firstLb)
            : $this->linkItem(1, $loadtime, '&laquo;', $firstLb);

        $left = $this->current <= 1
            ? $this->disabledItem('&lsaquo;', $back)
            : $this->linkItem($this->current - 1, $loadtime, '&lsaquo;', $back);

        $right = $this->current >= $this->pTotal
            ? $this->disabledItem('&rsaquo;', $next)
            : $this->linkItem($this->current + 1, $loadtime, '&rsaquo;', $next);

        $last = $this->current >= $this->pTotal
            ? $this->disabledItem('&raquo;', $lastLb)
            : $this->linkItem($this->pTotal, $loadtime, '&raquo;', $lastLb);

        return [
            'center'  => $center,
            'left'    => $first . $left,
            'right'   => $right . $last,
            'current' => $this->current,
            'total'   => $this->pTotal,
            'start'   => ($this->current - 1) * $this->limit + 1,
            'end'     => (int) \min($this->current * $this->limit, $this->total),
            'sql'     => $this->sqlQuery(),
        ];
    }

    /**
     * Plain (non-Bootstrap) text bar, same array shape as makeBootStrapBar().
     *
     * @return array{center:string,left:string,right:string,sql:string}
     */
    public function makeBar(): array
    {
        $bar = $this->makeBootStrapBar($this->urlPage);

        return [
            'center' => \strip_tags($bar['center'], '<a>'),
            'left'   => \strip_tags($bar['left'], '<a>'),
            'right'  => \strip_tags($bar['right'], '<a>'),
            'sql'    => $bar['sql'],
        ];
    }

    /**
     * Attribute-safe href. toPage (PHP_SELF by default) and urlOther are caller/request
     * input and queryStr is URL-encoded, so the whole href is HTML-escaped exactly once here.
     * Escaping cannot stop a "javascript:" destination, so toPage must be http(s) or a local
     * path; it is checked here, not in setToPage(), because the property is public.
     */
    private function pageHref(int $page, string $loadtime): string
    {
        $base = $this->toPage;
        if ('' !== $base) {
            $base = Output::navigationTarget($base, (string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        }
        $href = $base . $this->queryStr . $this->glue . $this->urlPage . '=' . $page . $loadtime;

        return HtmlBuilder::escape($href);
    }

    private function linkItem(int $page, string $loadtime, string $glyph, string $label): string
    {
        return \sprintf(
            '<li class="page-item"><a class="page-link" href="%s" aria-label="%s"><span aria-hidden="true">%s</span></a></li>',
            $this->pageHref($page, $loadtime),
            HtmlBuilder::escape($label),
            $glyph
        );
    }

    private function disabledItem(string $glyph, string $label): string
    {
        return \sprintf(
            '<li class="page-item disabled"><span class="page-link" aria-disabled="true" aria-label="%s">%s</span></li>',
            HtmlBuilder::escape($label),
            $glyph
        );
    }

    /**
     * Return a language constant if defined, otherwise the English fallback.
     * Keeps the class self-contained — no language file must be loaded first.
     */
    private static function label(string $constant, string $fallback): string
    {
        return \defined($constant) ? (string) \constant($constant) : $fallback;
    }
}
