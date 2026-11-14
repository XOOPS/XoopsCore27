<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Module;

use Xoops\ModuleTools\Bootstrap;

/**
 * One-call consumer-side runtime checks.
 *
 * Existing modules used to ask whether the mTools module was installed. XOOPS
 * 2.8 provides that API as a Core library, while this class preserves the old
 * readiness contract for entry points, install/update hooks, and blocks.
 * Historically each module copied a bespoke
 * `<dirname>_mtools_dependency_error()` function whose only per-module variance was
 * its name and two version strings. This class centralises that logic so every
 * consumer gets identical behavior and messages.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
final class ConsumerRuntime
{
    /**
     * Canonical readiness check. Returns '' when mtools is present and new enough,
     * otherwise a human-readable message describing what is wrong.
     */
    public static function dependencyError(
        string $minimumApiVersion = Bootstrap::API_VERSION,
        string $minimumModuleVersion = Bootstrap::MIN_MODULE_VERSION
    ): string {
        $status = Bootstrap::checkRuntime($minimumApiVersion, $minimumModuleVersion);

        return $status['ok'] ? '' : Bootstrap::statusMessage($status);
    }

    /**
     * Convenience boolean form of {@see self::dependencyError()}.
     */
    public static function isReady(
        string $minimumApiVersion = Bootstrap::API_VERSION,
        string $minimumModuleVersion = Bootstrap::MIN_MODULE_VERSION
    ): bool {
        return '' === self::dependencyError($minimumApiVersion, $minimumModuleVersion);
    }

    /**
     * Entry-point guard: on failure, redirect with the message and stop the request.
     *
     * Use at the top of public/admin entry points (where XOOPS is loaded), e.g.
     * `ConsumerRuntime::guard(XOOPS_URL);`.
     */
    public static function guard(
        string $redirectUrl,
        int $delay = 3,
        string $minimumApiVersion = Bootstrap::API_VERSION,
        string $minimumModuleVersion = Bootstrap::MIN_MODULE_VERSION
    ): void {
        $error = self::dependencyError($minimumApiVersion, $minimumModuleVersion);

        if ('' !== $error) {
            redirect_header($redirectUrl, $delay, $error);
            exit;
        }
    }

    /**
     * Install/update hook guard: on failure, record the error on the module and
     * report not-ready. Use as `if (!ConsumerRuntime::assertReady($module)) { return false; }`.
     */
    public static function assertReady(
        \XoopsModule $module,
        string $minimumApiVersion = Bootstrap::API_VERSION,
        string $minimumModuleVersion = Bootstrap::MIN_MODULE_VERSION
    ): bool {
        $error = self::dependencyError($minimumApiVersion, $minimumModuleVersion);

        if ('' !== $error) {
            $module->setErrors($error);

            return false;
        }

        return true;
    }
}
