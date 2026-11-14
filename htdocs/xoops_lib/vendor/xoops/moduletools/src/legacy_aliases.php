<?php

declare(strict_types=1);

/**
 * Lazy compatibility bridge for modules released against mTools 1.x.
 *
 * The alias is created only when an old class is requested, so modern sites pay
 * no class-loading cost and a separately installed legacy mTools can still serve
 * experimental classes that were deliberately not promoted.
 */

spl_autoload_register(static function (string $legacyClass): void {
    $legacyPrefix = 'XoopsModules\\Mtools\\';
    if (!str_starts_with($legacyClass, $legacyPrefix)) {
        return;
    }

    $modernClass = 'Xoops\\ModuleTools\\' . substr($legacyClass, strlen($legacyPrefix));
    $available = class_exists($modernClass)
        || interface_exists($modernClass)
        || trait_exists($modernClass);
    if (
        $available && !class_exists($legacyClass, false)
        && !interface_exists($legacyClass, false) && !trait_exists($legacyClass, false)
    ) {
        class_alias($modernClass, $legacyClass);
    }
});
