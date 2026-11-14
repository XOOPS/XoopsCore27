<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal\Installation;

/** @internal Compatibility implementation detail; not part of the ModuleTools public API. */
final readonly class InstallationPlan
{
    /**
     * @param list<string>               $uploadFolders
     * @param list<string>               $blankFileTargets
     * @param list<array{string, string}> $testFolderCopies
     */
    public function __construct(
        public array $uploadFolders,
        public array $blankFileTargets,
        public array $testFolderCopies,
        public string $blankFileSource,
    ) {
    }
}
