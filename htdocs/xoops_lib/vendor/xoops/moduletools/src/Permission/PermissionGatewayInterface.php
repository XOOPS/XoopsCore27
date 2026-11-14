<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Permission;

interface PermissionGatewayInterface
{
    /** @return list<int> */
    public function groupIds(string $name, int $itemId, int $moduleId): array;

    /** @param list<int> $groupIds @return list<int> */
    public function itemIds(string $name, array $groupIds, int $moduleId): array;

    /** @param list<int> $groupIds */
    public function checkRight(string $name, int $itemId, array $groupIds, int $moduleId): bool;

    public function delete(string $name, int $itemId, int $moduleId): bool;

    public function add(string $name, int $itemId, int $groupId, int $moduleId): bool;
}
