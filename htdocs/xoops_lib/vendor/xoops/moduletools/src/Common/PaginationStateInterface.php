<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/**
 * Contract for pure pagination math: page count, current page, offsets and the page band.
 *
 * Implementations MUST be side-effect free — no `$_GET`/`$_SERVER` reads, no HTML, no
 * free-form SQL. This is the promotion-ready half of the legacy {@see Paginator}: the
 * renderer keeps the request-reading and markup, this computes the numbers.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
interface PaginationStateInterface
{
    /** Total rows in the unpaginated result. */
    public function total(): int;

    /** Rows per page. */
    public function limit(): int;

    /** Number of pages (>= 1). */
    public function pageCount(): int;

    /** Current page, 1-based, clamped to [1, pageCount]. */
    public function currentPage(): int;

    /** Zero-based row offset for the current page (for SQL LIMIT / OFFSET). */
    public function offset(): int;

    /** 1-based index of the first row shown on the current page (0 when empty). */
    public function rangeStart(): int;

    /** 1-based index of the last row shown on the current page (0 when empty). */
    public function rangeEnd(): int;

    public function hasPrevious(): bool;

    public function hasNext(): bool;

    public function isFirst(): bool;

    public function isLast(): bool;

    /** Current page band (group of up to pageLimit page links). */
    public function band(): int;

    /** First page number in the current band. */
    public function bandStart(): int;

    /** Last page number in the current band. */
    public function bandEnd(): int;

    /**
     * The page numbers in the current band.
     *
     * @return list<int>
     */
    public function pages(): array;

    /** A safe `LIMIT offset, length` clause (no ORDER BY, no user input). */
    public function limitClause(): string;
}
