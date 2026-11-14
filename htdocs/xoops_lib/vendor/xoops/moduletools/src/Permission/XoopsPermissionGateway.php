<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Permission;

final readonly class XoopsPermissionGateway implements PermissionGatewayInterface
{
    public function __construct(private object $handler)
    {
        foreach (['getGroupIds', 'getItemIds', 'checkRight', 'deleteByModule', 'addRight'] as $method) {
            if (!method_exists($handler, $method)) {
                throw new \InvalidArgumentException('The XOOPS group-permission handler is incomplete.');
            }
        }
    }

    public static function fromCore(): self
    {
        $handler = xoops_getHandler('groupperm');
        if (!is_object($handler)) {
            throw new \RuntimeException('XOOPS group-permission handler is unavailable.');
        }

        return new self($handler);
    }

    public function groupIds(string $name, int $itemId, int $moduleId): array
    {
        return array_values(array_map(intval(...), $this->handler->getGroupIds($name, $itemId, $moduleId)));
    }

    public function itemIds(string $name, array $groupIds, int $moduleId): array
    {
        return array_values(array_map(intval(...), $this->handler->getItemIds($name, $groupIds, $moduleId)));
    }

    public function checkRight(string $name, int $itemId, array $groupIds, int $moduleId): bool
    {
        return (bool) $this->handler->checkRight($name, $itemId, $groupIds, $moduleId);
    }

    public function delete(string $name, int $itemId, int $moduleId): bool
    {
        return (bool) $this->handler->deleteByModule($moduleId, $name, $itemId);
    }

    public function add(string $name, int $itemId, int $groupId, int $moduleId): bool
    {
        return (bool) $this->handler->addRight($name, $itemId, $groupId, $moduleId);
    }
}
