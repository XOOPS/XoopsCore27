<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Permission;

final readonly class ItemPermission
{
    public function __construct(
        private int $moduleId,
        private PermissionGatewayInterface $gateway,
    ) {
        if ($moduleId < 1) {
            throw new \InvalidArgumentException('A positive XOOPS module id is required.');
        }
    }

    public static function forModule(object $module): self
    {
        if (!method_exists($module, 'getVar')) {
            throw new \InvalidArgumentException('A XOOPS module object is required.');
        }

        return new self((int) $module->getVar('mid', 'n'), XoopsPermissionGateway::fromCore());
    }

    /** @return list<int> */
    public function grantedGroups(string $name, int $itemId): array
    {
        $this->assertArguments($name, $itemId);

        return $this->gateway->groupIds($name, $itemId, $this->moduleId);
    }

    /** @param list<int> $groupIds @return list<int> */
    public function grantedItems(string $name, array $groupIds): array
    {
        $this->assertName($name);
        $groups = $this->normalizeIds($groupIds);
        // Core builds no group predicate for an empty list and would answer for *any* group.
        if ([] === $groups) {
            return [];
        }

        return $this->gateway->itemIds($name, $groups, $this->moduleId);
    }

    /** @param list<int> $groupIds */
    public function isGranted(string $name, int $itemId, array $groupIds): bool
    {
        $this->assertArguments($name, $itemId);
        $groups = $this->normalizeIds($groupIds);
        if ([] === $groups) {
            return false;
        }
        // Core checkRight() drops the item filter for id 0, so a grant on ANY item would pass.
        // Item 0 (module-wide, e.g. 'add' on a new object) needs an exact grant; webmasters keep core's bypass.
        if (0 === $itemId) {
            return (\defined('XOOPS_GROUP_ADMIN') && \in_array((int) \XOOPS_GROUP_ADMIN, $groups, true))
                || [] !== \array_intersect($groups, $this->gateway->groupIds($name, 0, $this->moduleId));
        }

        return $this->gateway->checkRight($name, $itemId, $groups, $this->moduleId);
    }

    /** @param list<int> $groupIds */
    public function replace(string $name, int $itemId, array $groupIds): bool
    {
        $this->assertArguments($name, $itemId);
        $previous = $this->gateway->groupIds($name, $itemId, $this->moduleId);
        if (!$this->gateway->delete($name, $itemId, $this->moduleId)) {
            return false;
        }
        if ($this->addAll($name, $itemId, $this->normalizeIds($groupIds))) {
            return true;
        }
        // ponytail: no transaction on the gateway; best-effort restore of the previous grants
        $this->gateway->delete($name, $itemId, $this->moduleId);
        $this->addAll($name, $itemId, $this->normalizeIds($previous));
        return false;
    }

    /** @param list<int> $groupIds */
    private function addAll(string $name, int $itemId, array $groupIds): bool
    {
        return array_all($groupIds, fn($groupId) => $this->gateway->add($name, $itemId, $groupId, $this->moduleId));
    }

    public function delete(string $name, int $itemId): bool
    {
        $this->assertArguments($name, $itemId);

        return $this->gateway->delete($name, $itemId, $this->moduleId);
    }

    private function assertArguments(string $name, int $itemId): void
    {
        $this->assertName($name);
        if ($itemId < 0) {
            throw new \InvalidArgumentException('Permission item ids cannot be negative.');
        }
    }

    private function assertName(string $name): void
    {
        if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $name)) {
            throw new \InvalidArgumentException('Invalid permission name.');
        }
    }

    /** @param array<int, mixed> $ids @return list<int> */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }
}
