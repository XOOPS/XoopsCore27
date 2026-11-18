<?php
/**
 * Published security advisories for the bundled Composer packages
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  System
 * @package   system
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 * @since     2.7.5
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Asks Packagist which of the installed packages have a published advisory that
 * covers the installed version. Only package names leave the site. The answer is
 * cached, so the request runs at most once a day (once an hour while it fails).
 */
class ComposerAdvisories
{
    public const API_URL = 'https://packagist.org/api/security-advisories/';
    // A good answer and a failed attempt are stored apart, and the good one is read
    // first: a failed attempt can then never replace or hide a good answer.
    public const CACHE_KEY_OK     = 'system_composer_advisories';
    public const CACHE_KEY_FAILED = 'system_composer_advisories_failed';
    public const TTL_OK           = 86400;
    public const TTL_FAILED       = 3600;

    /**
     * Advisories that cover the installed version of each package.
     *
     * Never throws and never raises: any failure is reported as "not available".
     *
     * @param array<int, array{name: string, version: string}> $packages installed packages
     * @param callable|null                                   $fetch    receives the request URL, returns the body or false
     *
     * @return array{available: bool, advisories: array<string, array<int, array{title: string, link: string, cve: string, severity: string}>>}
     */
    public static function forPackages(array $packages, ?callable $fetch = null): array
    {
        $unavailable = ['available' => false, 'advisories' => []];

        // A cache file that cannot be read and a stream that cannot connect report with a PHP
        // warning. The answer for all of them is "not available", so they stay off the page and the log.
        set_error_handler(static fn(): bool => true, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);
        try {
            $versions = [];
            foreach ($packages as $package) {
                $name = (string) ($package['name'] ?? '');
                if (1 === preg_match('#^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$#', $name)) {
                    $versions[$name] = (string) ($package['version'] ?? '');
                }
            }
            if ([] === $versions) {
                return $unavailable;
            }
            ksort($versions);

            // The installed set is part of the cached answer: a library update asks again.
            $installed = md5((string) json_encode($versions));
            $cached    = self::cached($installed);
            if (null !== $cached) {
                return ['available' => true, 'advisories' => $cached];
            }
            $failed = XoopsCache::read(self::CACHE_KEY_FAILED);
            if (is_array($failed) && ($failed['installed'] ?? '') === $installed) {
                return $unavailable;
            }

            $result = self::ask($versions, $fetch ?? [self::class, 'fetch']);
            if ($result['available']) {
                XoopsCache::write(self::CACHE_KEY_OK, ['installed' => $installed, 'advisories' => $result['advisories']], self::TTL_OK);
            } else {
                XoopsCache::write(self::CACHE_KEY_FAILED, ['installed' => $installed], self::TTL_FAILED);
            }

            return $result;
        } catch (\Throwable $e) {
            return $unavailable;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * The stored good answer for this set of installed packages, or null when there is none.
     *
     * What comes out of the cache is built again field by field, like a fresh answer,
     * so a damaged or altered entry cannot put anything else on the page.
     *
     * @param string $installed fingerprint of the installed packages and versions
     *
     * @return array<string, array<int, array{title: string, link: string, cve: string, severity: string}>>|null
     */
    private static function cached(string $installed): ?array
    {
        $cached = XoopsCache::read(self::CACHE_KEY_OK);
        if (!is_array($cached) || ($cached['installed'] ?? '') !== $installed || !is_array($cached['advisories'] ?? null)) {
            return null;
        }
        $advisories = [];
        foreach ($cached['advisories'] as $name => $list) {
            foreach (is_array($list) ? $list : [] as $advisory) {
                if (is_array($advisory)) {
                    $advisories[(string) $name][] = self::advisory($advisory);
                }
            }
        }

        return $advisories;
    }

    /**
     * Request the advisories and keep those that cover an installed version.
     *
     * @param array<string, string> $versions installed version per package name
     * @param callable              $fetch    receives the request URL, returns the body or false
     *
     * @return array{available: bool, advisories: array<string, array<int, array{title: string, link: string, cve: string, severity: string}>>}
     */
    private static function ask(array $versions, callable $fetch): array
    {
        $unavailable = ['available' => false, 'advisories' => []];

        try {
            $query = [];
            foreach (array_keys($versions) as $name) {
                $query[] = 'packages%5B%5D=' . rawurlencode($name);
            }
            $body = $fetch(self::API_URL . '?' . implode('&', $query));
            $data = is_string($body) ? json_decode($body, true) : null;
            if (!is_array($data) || !is_array($data['advisories'] ?? null)) {
                return $unavailable;
            }
            // An answer that is not in the expected form is no answer: "nothing found" must not rest on it.
            foreach ($data['advisories'] as $list) {
                foreach (is_array($list) ? $list : [null] as $advisory) {
                    if (!is_array($advisory) || !is_string($advisory['affectedVersions'] ?? null) || '' === $advisory['affectedVersions']) {
                        return $unavailable;
                    }
                }
            }

            return ['available' => true, 'advisories' => self::match($versions, $data['advisories'])];
        } catch (\Throwable $e) {
            return $unavailable;
        }
    }

    /**
     * Keep the advisories whose affected range holds the installed version.
     *
     * Ranges are read the way Composer reads them, so the result agrees with "composer audit".
     * When Composer cannot read the range or the installed version, the advisory is kept:
     * a package that cannot be compared is shown, not passed over.
     *
     * @param array<string, string> $versions   installed version per package name
     * @param array<mixed>          $advisories advisories per package name, as Packagist returns them
     *
     * @return array<string, array<int, array{title: string, link: string, cve: string, severity: string}>>
     */
    public static function match(array $versions, array $advisories): array
    {
        $found = [];
        foreach ($versions as $name => $version) {
            $list = $advisories[$name] ?? [];
            foreach (is_array($list) ? $list : [] as $advisory) {
                $range = is_array($advisory) ? ($advisory['affectedVersions'] ?? null) : null;
                if (!is_string($range) || '' === $range) {
                    continue;
                }
                try {
                    if (!\Composer\Semver\Semver::satisfies($version, $range)) {
                        continue;
                    }
                } catch (\UnexpectedValueException $e) {
                    // not comparable: keep the advisory
                }
                $found[$name][] = self::advisory($advisory);
            }
        }

        return $found;
    }

    /**
     * The four texts the panel prints for one advisory.
     *
     * @param array<mixed> $advisory
     *
     * @return array{title: string, link: string, cve: string, severity: string}
     */
    private static function advisory(array $advisory): array
    {
        $link = self::text($advisory['link'] ?? '', 500);

        return [
            'title'    => self::text($advisory['title'] ?? '', 200),
            // the link is printed into an href: nothing but an https address gets there
            'link'     => (str_starts_with($link, 'https://') && false !== filter_var($link, FILTER_VALIDATE_URL)) ? $link : '',
            'cve'      => self::text($advisory['cve'] ?? '', 40),
            'severity' => self::text($advisory['severity'] ?? '', 20),
        ];
    }

    /**
     * A remote value as one line of plain text, cut to a length.
     *
     * @param mixed $value
     * @param int   $length
     *
     * @return string
     */
    private static function text($value, int $length): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $value)), 0, $length, 'UTF-8');
    }

    /**
     * Send the request: public https target only, short timeouts.
     *
     * @param string $url
     *
     * @return string|false
     */
    private static function fetch(string $url)
    {
        XoopsLoad::load('xoopshttpget');
        $http = new XoopsHttpGet($url);
        $http->setTimeouts(3, 5);

        return $http->fetch();
    }
}
