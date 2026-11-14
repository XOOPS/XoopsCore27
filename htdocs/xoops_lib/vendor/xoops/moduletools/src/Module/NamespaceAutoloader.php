<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Module;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Register a module namespace with a PSR-4 fast path and a legacy filename fallback.
 *
 * The fallback lets converted modules retain historical filenames while callers move
 * to namespaced class references. It is built lazily and only for the requested
 * module namespace.
 */
final class NamespaceAutoloader
{
    /** @var array<string, true> */
    private static array $registrations = [];

    public static function register(string $namespacePrefix, string $classDirectory): void
    {
        $namespacePrefix = trim($namespacePrefix, '\\') . '\\';
        $classDirectory  = rtrim(str_replace('\\', '/', $classDirectory), '/') . '/';
        $registrationKey = strtolower($namespacePrefix . '|' . $classDirectory);

        if (isset(self::$registrations[$registrationKey])) {
            return;
        }
        if (!is_dir($classDirectory)) {
            throw new RuntimeException(sprintf('Module class directory does not exist: %s', $classDirectory));
        }

        self::$registrations[$registrationKey] = true;

        spl_autoload_register(
            static function (string $class) use ($namespacePrefix, $classDirectory): void {
                if (!str_starts_with($class, $namespacePrefix)) {
                    return;
                }

                $relativeClass = ltrim(substr($class, strlen($namespacePrefix)), '\\');
                // Every segment must be a PHP identifier, so "..", "/" and "." never reach the path.
                if (!preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/', $relativeClass)) {
                    return;
                }

                $psrFile = $classDirectory . str_replace('\\', '/', $relativeClass) . '.php';
                if (is_file($psrFile)) {
                    require_once $psrFile;

                    return;
                }

                /** @var array<string, string>|null $classMap */
                static $classMap = null;
                $classMap ??= self::buildClassMap($classDirectory, $namespacePrefix);
                $mappedFile = $classMap[strtolower($class)] ?? null;
                if ($mappedFile !== null) {
                    require_once $mappedFile;
                }
            },
        );
    }

    /** @return array<string, string> */
    private static function buildClassMap(string $classDirectory, string $namespacePrefix): array
    {
        $classMap = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($classDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            foreach (self::declaredTypes((string) file_get_contents($path)) as $class) {
                if (str_starts_with($class . '\\', $namespacePrefix)) {
                    $classMap[strtolower($class)] = $path;
                }
            }
        }

        return $classMap;
    }

    /** @return list<string> */
    private static function declaredTypes(string $source): array
    {
        $tokens     = token_get_all($source);
        $namespace  = '';
        $types      = [];
        $typeTokens = [T_CLASS, T_INTERFACE, T_TRAIT];
        if (defined('T_ENUM')) {
            $typeTokens[] = T_ENUM;
        }

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = self::readNamespace($tokens, $index + 1);
                continue;
            }
            if (!in_array($token[0], $typeTokens, true)) {
                continue;
            }
            if ($token[0] === T_CLASS) {
                $previous = self::previousSignificantToken($tokens, $index);
                if (is_array($previous) && $previous[0] === T_NEW) {
                    continue;
                }
            }

            $name = self::nextTypeName($tokens, $index + 1);
            if ($name !== null) {
                $types[] = ltrim($namespace . '\\' . $name, '\\');
            }
        }

        return $types;
    }

    /** @param array<int, mixed> $tokens */
    private static function readNamespace(array $tokens, int $start): string
    {
        $namespace = '';
        $nameTokens = [T_STRING, T_NS_SEPARATOR];
        if (defined('T_NAME_QUALIFIED')) {
            $nameTokens[] = T_NAME_QUALIFIED;
        }

        for ($index = $start, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];
            if ($token === ';' || $token === '{') {
                break;
            }
            if (is_array($token) && in_array($token[0], $nameTokens, true)) {
                $namespace .= $token[1];
            }
        }

        return trim($namespace, '\\');
    }

    /** @param array<int, mixed> $tokens */
    private static function nextTypeName(array $tokens, int $start): ?string
    {
        for ($index = $start, $count = count($tokens); $index < $count; ++$index) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
            if (!is_array($token) && ($token === '{' || $token === '(')) {
                return null;
            }
        }

        return null;
    }

    /** @param array<int, mixed> $tokens */
    private static function previousSignificantToken(array $tokens, int $index): mixed
    {
        for ($position = $index - 1; $position >= 0; --$position) {
            $token = $tokens[$position];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }
}
