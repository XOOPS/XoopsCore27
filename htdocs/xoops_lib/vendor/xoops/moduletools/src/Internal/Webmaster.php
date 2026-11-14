<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal;

/**
 * Whether the current user is a webmaster (member of the core Webmasters group).
 *
 * XoopsUser::isAdmin() without an id tests the CURRENT module, so a module-only admin passes
 * it. Site-wide filesystem actions (paths anywhere under the XOOPS root, new module trees)
 * must use this check instead.
 *
 * @internal
 */
final class Webmaster
{
    public static function isCurrentUser(): bool
    {
        $user = self::runtimeUser();
        if (!$user instanceof \XoopsUser || !\defined('XOOPS_GROUP_ADMIN')) {
            return false;
        }
        $groups = $user->getGroups();

        return \is_array($groups) && \in_array((int) \XOOPS_GROUP_ADMIN, \array_map(\intval(...), $groups), true);
    }

    /** @legacy-global-accessor */
    private static function runtimeUser(): mixed
    {
        return $GLOBALS['xoopsUser'] ?? null;
    }
}
