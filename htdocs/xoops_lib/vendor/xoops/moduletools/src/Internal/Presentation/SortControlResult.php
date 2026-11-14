<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal\Presentation;

/** @internal Compatibility implementation detail; not part of the ModuleTools public API. */
final readonly class SortControlResult
{
    public function __construct(
        public string $formAction,
        public string $ascendingUrl,
        public string $descendingUrl,
        public string $ascendingIcon,
        public string $descendingIcon,
    ) {
    }
}
