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

use Xoops\Helpers\Service\Cache;
use Xoops\Helpers\Utility\Retry;
use Xoops\ModuleTools\Internal\Update\VersionUpdatePolicy;

/**
 * Optional remote update checks. Keep this separate from local version checks.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
final class UpdateChecker
{
    /**
     * @param string|null $default    kept for signature compatibility; the download link always
     *                                points at the latest release tag, so it is no longer read
     * @param string|null $repository GitHub "owner/repo"; defaults to the module's
     *                                `github_repo` manifest entry, then XoopsModules25x/{dirname}
     */
    public static function checkVerModule(\Xmf\Module\Helper $helper, ?string $source = 'github', ?string $default = 'master', ?string $repository = null): ?array
    {
        $module = $helper->getModule();
        $moduleDirName = $module instanceof \XoopsModule
            ? basename((string)$module->getVar('dirname'))
            : basename(\dirname(__DIR__, 2));
        $moduleDirNameUpper = mb_strtoupper($moduleDirName);
        $manifestRepository = $module instanceof \XoopsModule ? $module->getInfo('github_repo') : false;
        $repository ??= (\is_string($manifestRepository) && '' !== $manifestRepository ? $manifestRepository : null)
        ?? 'XoopsModules25x/' . $moduleDirName;
        if (1 !== \preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository)) {
            return null;
        }

        if ('github' !== $source || !function_exists('curl_init')) {
            return null;
        }

        // Cache the resolved update result for an hour to avoid hammering the
        // GitHub API on every admin page load. The whole computation is
        // deterministic given the remote release data, so caching the final
        // ?array preserves the existing return contract.
        // The cached answer depends on the installed version too: after an update the
        // "new version" banner must not survive until the hour is up.
        $moduleVersion = $module instanceof \XoopsModule
            ? $module->getInfo('version') . '_' . $module->getInfo('module_status')
            : '0.0.0';

        return Cache::remember(
            'mtools_update_' . $moduleDirName . '_' . \substr(\md5($repository . '|' . $moduleVersion), 0, 12),
            3600,
            static function () use ($repository, $moduleVersion): ?array {
                $infoReleasesUrl = "https://api.github.com/repos/$repository/releases/latest";

                // Transient network read — retry twice before giving up. On
                // exhausted retries, rescue() emits the cURL error and yields
                // false, preserving the original "return null on failure" contract.
                $curlReturn = Retry::rescue(
                    static fn() => Retry::retry(2, static function () use ($infoReleasesUrl) {
                        $curlHandle = curl_init();
                        if (false === $curlHandle) {
                            throw new \RuntimeException('Unable to initialise cURL');
                        }

                        curl_setopt($curlHandle, CURLOPT_URL, $infoReleasesUrl);
                        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 5);
                        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10);
                        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, true);
                        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, ['User-Agent: XOOPS mtools']);

                        $result = curl_exec($curlHandle);
                        if (false === $result) {
                            $error = curl_error($curlHandle);
                            throw new \RuntimeException($error);
                        }

                        return $result;
                    }),
                    false,
                    static function (\Throwable $e): bool {
                        trigger_error($e->getMessage());

                        return true;
                    }
                );

                if (false === $curlReturn) {
                    return null;
                }

                if (str_contains((string)$curlReturn, 'Not Found')) {
                    return null;
                }

                $release = json_decode((string)$curlReturn, false);
                if (!is_object($release) || !isset($release->tag_name) || !is_string($release->tag_name) || '' === $release->tag_name || !empty($release->draft)) {
                    return null;
                }

                $latestVersionLink = sprintf("https://github.com/$repository/archive/%s.zip", rawurlencode($release->tag_name));
                $latestVersion     = $release->tag_name;
                $prerelease        = (bool)($release->prerelease ?? false);
                $updateLabel       = defined('_CO_MTOOLS_NEW_VERSION')
                    ? constant('_CO_MTOOLS_NEW_VERSION')
                    : 'New version: ';

                if (new VersionUpdatePolicy()->hasStableUpdate($moduleVersion, $latestVersion, $prerelease)) {
                    return [
                        $updateLabel . $latestVersion,
                        $latestVersionLink,
                    ];
                }

                return null;
            }
        );
    }
}
