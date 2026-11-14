<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Admin;

final readonly class ObjectRow
{
    public function __construct(
        public string $key,
        public string|false $valueMethod = false,
        public bool $header = false,
        public string|false $class = false,
    ) {
    }
}
