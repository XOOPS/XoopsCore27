<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal\Confirmation;

/** @internal Compatibility implementation detail; not part of the ModuleTools public API. */
final readonly class ConfirmationModel
{
    /** @param array<string, mixed> $hiddens */
    public function __construct(
        public array $hiddens,
        public string $action,
        public string $object,
        public string $title,
        public string $label,
    ) {
    }
}
