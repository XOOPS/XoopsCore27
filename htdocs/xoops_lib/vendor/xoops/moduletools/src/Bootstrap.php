<?php

declare(strict_types=1);

namespace Xoops\ModuleTools;

/**
 * Stable package and API version contract for module consumers.
 *
 * The library is part of the XOOPS runtime, so readiness no longer depends on
 * an installed or active `mtools` module row.
 */
final class Bootstrap
{
    public const string VERSION = '1.6.0';
    public const string API_VERSION = '1.2.0';
    public const string MIN_MODULE_VERSION = '1.1.0';
    public const string MODULE_DIRNAME = 'mtools';
    public const string PACKAGE = 'xoops/moduletools';

    public static function apiVersion(): string
    {
        return self::API_VERSION;
    }

    /**
     * Preserve the former mTools runtime-check result shape for old consumers.
     *
     * @return array{ok: bool, errors: list<string>, module_version: string, api_version: string}
     */
    public static function checkRuntime(
        string $minimumApiVersion = self::API_VERSION,
        string $minimumModuleVersion = self::MIN_MODULE_VERSION,
        bool $requireActive = false,
    ): array {
        unset($requireActive);
        $errors = [];

        if (version_compare(self::API_VERSION, self::normalizeVersion($minimumApiVersion), '<')) {
            $errors[] = sprintf(
                'ModuleTools API %s is required; API %s is available.',
                $minimumApiVersion,
                self::API_VERSION,
            );
        }
        if (version_compare(self::VERSION, self::normalizeVersion($minimumModuleVersion), '<')) {
            $errors[] = sprintf(
                'ModuleTools %s is required; library %s is available.',
                $minimumModuleVersion,
                self::VERSION,
            );
        }

        return [
            'ok' => [] === $errors,
            'errors' => $errors,
            'module_version' => self::VERSION,
            'api_version' => self::API_VERSION,
        ];
    }

    public static function assertRuntime(
        string $minimumApiVersion = self::API_VERSION,
        string $minimumModuleVersion = self::MIN_MODULE_VERSION,
        bool $requireActive = false,
    ): void {
        $status = self::checkRuntime($minimumApiVersion, $minimumModuleVersion, $requireActive);
        if (!$status['ok']) {
            throw new \RuntimeException(self::statusMessage($status));
        }
    }

    /** @param array{errors?: list<string>} $status */
    public static function statusMessage(array $status): string
    {
        return implode(' ', $status['errors'] ?? []);
    }

    private static function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if ('' === $version) {
            return '0.0.0';
        }
        if (1 === preg_match('/^\d+$/', $version) && (int) $version >= 100) {
            return number_format(((int) $version) / 100, 2, '.', '');
        }

        return preg_replace('/-(alpha|beta|rc)\d*/i', '', $version) ?? $version;
    }
}
