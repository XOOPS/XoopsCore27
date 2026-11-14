<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal;

/**
 * Load language catalogs owned by the ModuleTools package.
 *
 * XMF's module language loader intentionally resolves files below
 * `modules/{dirname}`. ModuleTools is a Composer package, so its catalogs need
 * a small package-local loader rather than a synthetic module Helper.
 *
 * @internal
 */
final class PackageLanguage
{
    public static function load(string $catalog, string $language = 'english'): bool
    {
        if (basename($catalog) !== $catalog || 1 !== preg_match('/^[A-Za-z0-9_-]+$/D', $catalog)) {
            return false;
        }

        if (basename($language) !== $language || 1 !== preg_match('/^[A-Za-z0-9_-]+$/D', $language)) {
            $language = 'english';
        }

        $base = dirname(__DIR__, 2) . '/resources/language';
        $paths = [
            $base . '/' . $language . '/' . $catalog . '.php',
            $base . '/english/' . $catalog . '.php',
        ];

        foreach (array_unique($paths) as $path) {
            if (is_file($path)) {
                include_once $path;

                return true;
            }
        }

        return false;
    }
}
