<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal\Presentation;

/** @internal Compatibility implementation detail; not part of the ModuleTools public API. */
final class SortControlBuilder
{
    public function build(
        string $requestUri,
        string $scriptName,
        int $start,
        string $currentOrder,
        string $currentSort,
        string $requestedSort,
    ): SortControlResult {
        $ascendingIcon  = $requestedSort === $currentSort && 'asc' === $currentOrder ? 'selasc.png' : 'asc.png';
        $descendingIcon = $requestedSort === $currentSort && 'desc' === $currentOrder ? 'seldesc.png' : 'desc.png';
        $baseUrl = $scriptName . '?start=' . (int) $start . '&sort=' . rawurlencode($requestedSort) . '&order=';

        return new SortControlResult(
            formAction: $requestUri,
            ascendingUrl: $baseUrl . 'asc',
            descendingUrl: $baseUrl . 'desc',
            ascendingIcon: $ascendingIcon,
            descendingIcon: $descendingIcon,
        );
    }
}
