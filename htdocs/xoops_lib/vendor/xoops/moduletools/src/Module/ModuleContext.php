<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Module;

use Xoops\Helpers\Service\Config;
use Xoops\Helpers\Service\Path;
use Xoops\Helpers\Service\Url;

/**
 * Immutable per-module context: dirname, filesystem paths, URLs, upload paths, config.
 *
 * Replaces the 20–50 line `define('{UP}_URL', XOOPS_URL.'/modules/'.$dir)` constant block
 * every consumer copies into `include/common.php`. Built on the xoops/helpers
 * {@see Path} / {@see Url} / {@see Config} services, so path/URL building is consistent
 * and testable instead of hand-concatenated.
 *
 * Use the accessors for new code (`$ctx->url('admin/index.php')`); call
 * {@see self::defineConstants()} once to keep the legacy `{UP}_*` constants working for
 * code that still reads them.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 * @deprecated since 1.4.0. Use Xoops\Helpers\Service\Path, Url and Config directly; retained through XOOPS 2.8.
 */
final readonly class ModuleContext
{
    private function __construct(public string $dirname)
    {
        // An empty dirname would define bare _URL / _PATH / _ADMIN constants and resolve to modules/.
        if ('' === $dirname) {
            throw new \InvalidArgumentException('A module dirname is required to build a ModuleContext.');
        }
    }

    /**
     * Build a context from a module dirname (e.g. `ModuleContext::for('quotes')`).
     */
    public static function for(string $dirname): self
    {
        return new self(\basename($dirname));
    }

    /**
     * Build a context from a module Helper (reads the module's dirname).
     */
    public static function fromHelper(\Xmf\Module\Helper $helper): self
    {
        $module  = $helper->getModule();
        $dirname = ($module instanceof \XoopsModule) ? (string)$module->getVar('dirname', 'n') : '';

        return new self($dirname);
    }

    // ---- filesystem paths (no trailing slash unless noted) ----

    /** Module root path, optionally with a sub-path: `path('class/Foo.php')`. */
    public function path(string $sub = ''): string
    {
        return Path::module($this->dirname, $sub);
    }

    /** Module admin path. */
    public function adminPath(string $sub = ''): string
    {
        return Path::module($this->dirname, self::join('admin', $sub));
    }

    /** Module assets/images path. */
    public function imagesPath(string $sub = ''): string
    {
        return Path::module($this->dirname, self::join('assets/images', $sub));
    }

    /** Module uploads path (under XOOPS uploads), e.g. `uploadPath('category')`. */
    public function uploadPath(string $sub = ''): string
    {
        return Path::moduleUpload($this->dirname, $sub);
    }

    // ---- URLs (no trailing slash unless noted) ----

    /** Module URL, optionally with a sub-path + query: `url('index.php')`. */
    public function url(string $sub = ''): string
    {
        return Url::module($this->dirname, $sub);
    }

    /** Module admin URL. */
    public function adminUrl(string $sub = ''): string
    {
        return Url::module($this->dirname, self::join('admin', $sub));
    }

    /** Module assets/images URL. */
    public function imagesUrl(string $sub = ''): string
    {
        return Url::module($this->dirname, self::join('assets/images', $sub));
    }

    /** Module uploads URL, e.g. `uploadUrl('category/'.rawurlencode($file))`. */
    public function uploadUrl(string $sub = ''): string
    {
        return Url::moduleUpload($this->dirname, $sub);
    }

    // ---- config ----

    /** Read a module preference: `config('itemsperpage', 10)`. */
    public function config(string $key, mixed $default = null): mixed
    {
        return Config::get($this->dirname . '.' . $key, $default);
    }

    /**
     * Define the standard legacy `{UP}_*` constants for this module (idempotent), where
     * `{UP}` is the uppercased dirname. Reproduces the conventional constant block so a
     * consumer's `include/common.php` can drop ~20 lines for one call. Trailing-slash
     * conventions match the historical block (IMAGES/ADMIN/CACHE end with `/`, ROOT/URL/
     * UPLOAD do not). Module-specific constants stay in the consumer.
     */
    public function defineConstants(): void
    {
        $up = \mb_strtoupper($this->dirname);
        if (\defined($up . '_URL')) {
            return;
        }
        $path = $this->path();
        $url  = $this->url();

        \define($up . '_DIRNAME', $this->dirname);
        \define($up . '_ROOT_PATH', $path);
        \define($up . '_PATH', $path);
        \define($up . '_URL', $url);
        \define($up . '_IMAGES_URL', $url . '/assets/images/');
        \define($up . '_IMAGES_PATH', $path . '/assets/images/');
        \define($up . '_ADMIN_URL', $url . '/admin/');
        \define($up . '_ADMIN_PATH', $path . '/admin/');
        \define($up . '_ADMIN', $url . '/admin/index.php');
        \define($up . '_UPLOAD_URL', $this->uploadUrl());
        \define($up . '_UPLOAD_PATH', $this->uploadPath());
        \define($up . '_CACHE_PATH', $this->uploadPath() . '/');
    }

    private static function join(string $base, string $sub): string
    {
        return '' === $sub ? $base : $base . '/' . \ltrim($sub, '/');
    }
}
