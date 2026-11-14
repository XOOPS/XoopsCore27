<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal\Update;

/** @internal Compatibility implementation detail; not part of the ModuleTools public API. */
final class VersionUpdatePolicy
{
    public function hasStableUpdate(string $installedVersion, string $latestVersion, bool $prerelease): bool
    {
        return !$prerelease
            && version_compare($this->normalize($installedVersion), $this->normalize($latestVersion), '<');
    }

    public function normalize(string $version): string
    {
        $version = str_replace(' ', '', mb_strtolower($version));
        $version = preg_replace('/^v(?=\d)/', '', $version) ?? $version;

        return str_contains($version, 'final') ? str_replace(['_', 'final'], '', $version) : $version;
    }
}
