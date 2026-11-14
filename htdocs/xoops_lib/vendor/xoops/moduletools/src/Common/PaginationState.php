<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/**
 * Pure pagination math extracted from {@see Paginator}.
 *
 * Given a total row count, a page size, the requested page and a band size, it computes
 * the page count, the clamped current page, the row offset/range and the page band — with
 * NO `$_GET`/`$_SERVER` reads, NO HTML and NO free-form SQL. That purity makes it the
 * promotion-ready half of the pagination story (a candidate for xoops/helpers or XMF);
 * the legacy {@see Paginator} now delegates its math here and keeps only the
 * request-reading and Bootstrap markup.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
final readonly class PaginationState implements PaginationStateInterface
{
    private int $total;
    private int $limit;
    private int $pageLimit;
    private int $pageCount;
    private int $currentPage;

    /**
     * @param int $total       total rows in the unpaginated result
     * @param int $limit       rows per page (coerced to >= 1)
     * @param int $currentPage requested page, 1-based (clamped to [1, pageCount])
     * @param int $pageLimit   page links per band (coerced to >= 1)
     */
    public function __construct(int $total, int $limit = 20, int $currentPage = 1, int $pageLimit = 10)
    {
        $this->total       = \max(0, $total);
        $this->limit       = $limit > 0 ? $limit : 20;
        $this->pageLimit   = $pageLimit > 0 ? $pageLimit : 10;
        $this->pageCount   = (int) \max(1, (int) \ceil($this->total / $this->limit));
        $this->currentPage = \max(1, \min($currentPage, $this->pageCount));
    }

    public function total(): int
    {
        return $this->total;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function pageCount(): int
    {
        return $this->pageCount;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function offset(): int
    {
        return ($this->currentPage - 1) * $this->limit;
    }

    public function rangeStart(): int
    {
        return 0 === $this->total ? 0 : $this->offset() + 1;
    }

    public function rangeEnd(): int
    {
        return 0 === $this->total ? 0 : (int) \min($this->currentPage * $this->limit, $this->total);
    }

    public function hasPrevious(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNext(): bool
    {
        return $this->currentPage < $this->pageCount;
    }

    public function isFirst(): bool
    {
        return 1 === $this->currentPage;
    }

    public function isLast(): bool
    {
        return $this->currentPage === $this->pageCount;
    }

    public function band(): int
    {
        return (int) \ceil($this->currentPage / $this->pageLimit);
    }

    public function bandStart(): int
    {
        return ($this->band() - 1) * $this->pageLimit + 1;
    }

    public function bandEnd(): int
    {
        return (int) \min($this->pageCount, $this->band() * $this->pageLimit);
    }

    public function pages(): array
    {
        return \range($this->bandStart(), $this->bandEnd());
    }

    public function limitClause(): string
    {
        return \sprintf(' LIMIT %d, %d', $this->offset(), $this->limit);
    }
}
