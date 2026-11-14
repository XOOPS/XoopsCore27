<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.
*/

/**
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author    XOOPS Development Team
 */



// require_once \dirname(__DIR__, 2) . '/include/common.php';

/**
 * Class Configurator
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
class Configurator
{
    public string $name;
    /** @var array<string, mixed> */
    public array $paths = [];
    /** @var list<string> */
    public array $uploadFolders = [];
    /** @var list<string> */
    public array $copyBlankFiles = [];
    /** @var list<array{0: string, 1: string}> */
    public array $copyTestFolders = [];
    /** @var list<string> */
    public array $templateFolders = [];
    /** @var list<string> */
    public array $oldFiles = [];
    /** @var list<string> */
    public array $oldFolders = [];
    /** @var array<string, string> */
    public array $renameTables = [];
    /** @var array<string, mixed> */
    public array $renameColumns = [];
    /** @var array<string, mixed> */
    public array $moduleStats = [];
    public string $modCopyright;
    /** @var array<string, mixed> */
    public array $icons = [];
    private readonly string $baseDir;
    private readonly ModuleConfig $moduleConfig;

    /**
     * Named constructor: build a Configurator for the CONSUMER module from its Helper.
     *
     * Prefer this over `new Configurator(...)` — it makes the consumer's base directory
     * explicit and avoids the no-argument footgun (see the constructor).
     *
     * @since 1.2.0
     */
    public static function forModule(\Xmf\Module\Helper $helper): self
    {
        return new self($helper->path());
    }

    /**
     * Configurator constructor.
     *
     * The base directory MUST be supplied (e.g. `$helper->path()` or {@see self::forModule()}).
     * A missing/empty directory throws rather than silently falling back to mtools' OWN
     * directory — that fallback used to load mtools' config for the consumer, a silent bug.
     *
     * @param string|null $dir the CONSUMER module base directory
     *
     * @throws \InvalidArgumentException when no base directory is given
     */
    public function __construct(?string $dir = null)
    {
        $dir = rtrim((string)$dir, '/\\');
        if ('' === $dir) {
            throw new \InvalidArgumentException(
                'Configurator requires the consumer module base directory; '
                . 'pass it explicitly (e.g. Configurator::forModule($helper) or new Configurator($helper->path())).'
            );
        }
        $this->baseDir = $dir;

        $configFile = $this->baseDir . '/config/config.php';
        if (!\is_file($configFile)) {
            throw new \RuntimeException('Missing config file: ' . $configFile);
        }
        $config = require $configFile;
        if (\is_array($config)) {
            // Older modules commonly return an associative array. Normalize it
            // at the library boundary so consumers can adopt ModuleTools without
            // rewriting an otherwise valid module configuration file.
            $config = (object) $config;
        }
        if (!\is_object($config)) {
            throw new \RuntimeException(
                \sprintf(
                    'Invalid config format in %s: expected array or object, got %s',
                    $configFile,
                    \gettype($config)
                )
            );
        }

        // Typed, null-safe view of config/config.php (the contract for new code).
        $this->moduleConfig = ModuleConfig::fromObject($config);

        // Back-compat: mirror the typed config into the public properties consumers read.
        $this->name            = $this->moduleConfig->name;
        $this->uploadFolders   = $this->moduleConfig->uploadFolders;
        $this->copyBlankFiles  = $this->moduleConfig->copyBlankFiles;
        $this->copyTestFolders = $this->moduleConfig->copyTestFolders;
        $this->templateFolders = $this->moduleConfig->templateFolders;
        $this->oldFiles        = $this->moduleConfig->oldFiles;
        $this->oldFolders      = $this->moduleConfig->oldFolders;
        $this->renameTables    = $this->moduleConfig->renameTables;
        $this->renameColumns   = $this->moduleConfig->renameColumns;
        $this->moduleStats     = $this->moduleConfig->moduleStats;
        $this->paths           = $this->moduleConfig->paths;
        $this->modCopyright    = $this->moduleConfig->modCopyright;

        $iconsFile = $this->baseDir . '/config/icons.php';
        $pathsFile = $this->baseDir . '/config/paths.php';
        if (\is_file($iconsFile)) {
            $this->icons = self::loadMap($iconsFile, 'icons');
        }
        if (\is_file($pathsFile)) {
            $this->paths = \array_replace($this->paths, self::loadMap($pathsFile, 'paths'));
        }
    }

    public function getPath(string $key): ?string
    {
        $value = $this->paths[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * Load a config map while accepting both the current flat format and the
     * legacy configurator object (`['paths' => [...]]` / `['icons' => [...]]`).
     *
     * @return array<string, mixed>
     */
    private static function loadMap(string $file, string $legacyProperty): array
    {
        $value = require $file;
        if (\is_object($value)) {
            $value = \get_object_vars($value);
        }
        if (!\is_array($value)) {
            throw new \RuntimeException(
                \sprintf('Invalid %s config format in %s: expected array or object, got %s', $legacyProperty, $file, \gettype($value))
            );
        }

        if (\array_key_exists($legacyProperty, $value)) {
            $value = $value[$legacyProperty];
            if (\is_object($value)) {
                $value = \get_object_vars($value);
            }
            if (!\is_array($value)) {
                throw new \RuntimeException(
                    \sprintf('Invalid legacy %s map in %s: expected array or object, got %s', $legacyProperty, $file, \gettype($value))
                );
            }
        }

        return $value;
    }

    /**
     * The typed, immutable view of this module's config/config.php.
     *
     * Prefer this over the legacy public properties for new code, e.g.
     * `$configurator->config()->uploadFolders`.
     *
     * @since 1.2.0
     */
    public function config(): ModuleConfig
    {
        return $this->moduleConfig;
    }
}
