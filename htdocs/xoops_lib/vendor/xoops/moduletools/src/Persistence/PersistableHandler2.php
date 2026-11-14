<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Persistence;

class PersistableHandler2 extends PersistableHandler
{
    public function __construct(?\XoopsDatabase $db = null, string $table = '', string $className = '', string $keyName = '', string $identifierName = '', string $itemName = '', string $summaryName = '', ?string $moduleName = null)
    {
        if (null === $moduleName) {
            $parts = explode('\\', trim($className, '\\'));
            $moduleName = isset($parts[1]) ? strtolower($parts[1]) : strtolower((string) ($parts[0] ?? ''));
        }
        parent::__construct($db, $itemName, $keyName, $identifierName, $summaryName, $moduleName, $table, $className);
    }
}
