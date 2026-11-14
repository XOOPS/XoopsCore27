<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Admin;

final readonly class ObjectColumn
{
    public function __construct(
        public string $key,
        public string $align = 'left',
        public int|false $width = false,
        public string|false $valueMethod = false,
        public array|false $parameters = false,
        public string|false $caption = false,
        public bool $sortable = true,
    ) {
    }
}
