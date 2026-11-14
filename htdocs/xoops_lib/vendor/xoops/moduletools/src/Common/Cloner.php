<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.
*/

use Xmf\Request;
use Xoops\Helpers\Utility\Filesystem;
use Xoops\Helpers\Utility\HtmlBuilder;
use Xoops\ModuleTools\Internal\PackageLanguage;
use Xoops\ModuleTools\Internal\PathContainment;
use Xoops\ModuleTools\Internal\PngMetadata;
use Xoops\ModuleTools\Internal\Webmaster;

/**
 * Clone a module directory under a new dirname, rewriting the old dirname in
 * every text file (lower, UPPER and Ucfirst forms) and stamping a new logo.
 *
 * `clone()` is pure filesystem work and needs no XOOPS bootstrap.
 * `handleAdminRequest()` is the shared body of a module's `admin/clone.php`.
 *
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author    trabis <lusopoemas@gmail.com>, XOOPS Development Team
 * @since     1.5.0
 */
final class Cloner
{
    /** Binary extensions copied verbatim instead of text-replaced. */
    private const array BINARY_EXTENSIONS = ['jpeg', 'jpg', 'gif', 'png', 'webp', 'ico', 'zip', 'ttf', 'woff', 'woff2', 'pdf'];

    /** Top-level entries never copied into a clone. */
    private const array SKIPPED_ENTRIES = ['node_modules', '.git', '.idea'];

    /** Generous for a 92x52 logo; refuses decompression bombs before decoding. */
    private const int MAX_LOGO_PIXELS = 1_000_000;

    /**
     * A dirname becomes a PHP namespace segment and a constant prefix in the clone, so it
     * must be a valid identifier: letters, digits and underscore only (a hyphen would emit
     * `namespace XoopsModules\My-news;` and every file in the clone would fail to parse).
     */
    public static function isValidDirname(string $dirname): bool
    {
        return 1 === \preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $dirname);
    }

    /**
     * Copy `$sourcePath` to a sibling directory named `$newDirname`.
     *
     * @throws \InvalidArgumentException on a bad name, missing source or existing target
     * @throws \RuntimeException         when a directory cannot be created
     */
    public static function clone(string $sourcePath, string $newDirname): string
    {
        $newDirname = \mb_strtolower($newDirname);
        if (!self::isValidDirname($newDirname)) {
            throw new \InvalidArgumentException('Invalid module dirname: ' . $newDirname);
        }
        // Resolve before deriving names: "quotes/." would otherwise give the basename "." (rewritten
        // in every copied file) and a target INSIDE the source, which copyTree() then re-copies.
        $resolved = \is_dir($sourcePath) ? \realpath($sourcePath) : false;
        if (false === $resolved) {
            throw new \InvalidArgumentException('Source module does not exist: ' . $sourcePath);
        }
        $resolved = \rtrim(\str_replace('\\', '/', $resolved), '/');
        // Name and place the clone by the LOGICAL path (a symlinked modules/quotes clones beside the
        // link, under its name); only a "." / ".." spelling falls back to the resolved path.
        $logical = (string) \preg_replace('#(?:/\.)+$#', '', \rtrim(\str_replace('\\', '/', $sourcePath), '/'));
        $sourcePath = 1 === \preg_match('#(?:^|/)\.\.?(?:/|$)#', $logical) ? $resolved : $logical;
        $oldDirname = \basename($sourcePath);
        $targetPath = \dirname($sourcePath) . '/' . $newDirname;
        // Containment is decided on resolved paths, whatever the spelling.
        $resolvedParent = \realpath(\dirname($sourcePath));
        $resolvedTarget = false === $resolvedParent ? '' : \rtrim(\str_replace('\\', '/', $resolvedParent), '/') . '/' . $newDirname;
        if ('' === $oldDirname || '' === $resolvedTarget || PathContainment::isInside($resolvedTarget, $resolved)) {
            throw new \InvalidArgumentException('Clone target cannot be inside the source: ' . $targetPath);
        }
        // Exclusive creation establishes ownership: an existing directory, file or symlink
        // (even one that appeared after a prior check) makes mkdir() fail, so only a
        // target this call created is ever cleaned up below.
        if (\file_exists($targetPath) || \is_link($targetPath) || !@\mkdir($targetPath)) {
            throw new \InvalidArgumentException('Target already exists: ' . $targetPath);
        }
        // Re-checked on the directory the OS actually created, so a link or junction chain the
        // parent resolution missed cannot put the target inside the tree copyTree() walks.
        $createdTarget = \realpath($targetPath);
        if (
            false === $createdTarget
            || PathContainment::isInside($createdTarget, $resolved)
            || PathContainment::isInsideByIdentity($createdTarget, $resolved)
        ) {
            @\rmdir($targetPath);
            throw new \InvalidArgumentException('Clone target cannot be inside the source: ' . $targetPath);
        }

        $patterns = [
            \mb_strtolower($oldDirname) => $newDirname,
            \mb_strtoupper($oldDirname) => \mb_strtoupper($newDirname),
            \ucfirst(\mb_strtolower($oldDirname)) => \ucfirst($newDirname),
        ];
        try {
            self::copyTree($sourcePath, $targetPath, \array_keys($patterns), \array_values($patterns), true);
        } catch (\Throwable $e) {
            // $targetPath did not exist before this call, so it is ours to remove; a partial
            // tree would otherwise block every retry with "Target already exists".
            // Filesystem::deleteDirectory() unlinks symlinks without following them.
            if (\is_dir($targetPath) && !Filesystem::deleteDirectory($targetPath)) {
                throw new \RuntimeException(\sprintf('Clone failed and the partial target "%s" could not be removed', $targetPath), 0, $e);
            }
            throw $e;
        }

        return $targetPath;
    }

    /**
     * Write the Ucfirst dirname onto `assets/images/logoModule.png` of a module.
     * Uses the module's own `assets/images/VeraBd.ttf` or the package copy.
     */
    public static function createLogo(string $modulePath, ?string $label = null): bool
    {
        $modulePath = \rtrim(\str_replace('\\', '/', $modulePath), '/');
        $logo       = $modulePath . '/assets/images/logoModule.png';
        $font       = $modulePath . '/assets/images/VeraBd.ttf';
        if (!\is_file($font)) {
            $font = \dirname(__DIR__, 2) . '/resources/fonts/VeraBd.ttf';
        }
        if (!\extension_loaded('gd') || !\function_exists('imagefttext') || !\is_file($font) || !self::isDecodableLogo($logo)) {
            return false;
        }
        $image = @\imagecreatefrompng($logo);
        if (false === $image) {
            return false;
        }
        $label ??= \ucfirst(\basename($modulePath));

        \imagealphablending($image, false);
        \imagesavealpha($image, true);
        $grey = \imagecolorallocate($image, 237, 237, 237);
        \imagefilledrectangle($image, 5, 35, 85, 46, $grey);
        $black = \imagecolorallocate($image, 0, 0, 0);
        $x     = (int) ((80 - \mb_strlen($label) * 6.5) / 2);
        \imagefttext($image, 8.5, 0, \max(2, $x), 45, $black, $font, $label);
        $ok = \imagepng($image, $logo);

        return $ok;
    }

    /**
     * A module logo is 92x52: reading the header first keeps a tiny, highly compressed PNG
     * from decoding into a huge raster. The PNG signature comes first: getimagesize() parses every
     * format it knows (compressed SWF is inflated in memory).
     */
    private static function isDecodableLogo(string $logo): bool
    {
        $isPng = \is_file($logo) && "\x89PNG\r\n\x1A\n" === @\file_get_contents($logo, false, null, 0, 8);
        $info  = $isPng ? @\getimagesize($logo) : false;

        return false !== $info && \IMAGETYPE_PNG === $info[2] && $info[0] * $info[1] <= self::MAX_LOGO_PIXELS
            && PngMetadata::isWithinBudget($logo);
    }

    /**
     * Shared body of a module's `admin/clone.php`: renders the form, or on POST
     * clones the helper's module and returns the result message.
     * Call between `xoops_cp_header()` and the admin footer.
     */
    public static function handleAdminRequest(\Xmf\Module\Helper $helper): string
    {
        PackageLanguage::load('common', (string) (self::runtimeGlobal('xoopsConfig')['language'] ?? 'english'));
        $dirname = $helper->dirname();

        if ('submit' !== Request::getString('op', '', 'POST')) {
            \xoops_load('XoopsFormLoader');
            $moduleName = \is_object($helper->getModule()) ? (string) $helper->getModule()->getVar('name', 'E') : $dirname;
            $form       = new \XoopsThemeForm(\sprintf(\_CO_MTOOLS_CLONE_TITLE, $moduleName), 'clone', 'clone.php', 'post', true);
            $name       = new \XoopsFormText(\_CO_MTOOLS_CLONE_NAME, 'clone', 20, 64, '');
            $name->setDescription(\_CO_MTOOLS_CLONE_NAME_DSC);
            $form->addElement($name, true);
            $form->addElement(new \XoopsFormHidden('op', 'submit'));
            $form->addElement(new \XoopsFormButton('', '', \_SUBMIT, 'submit'));

            return '<p>' . \_CO_MTOOLS_CLONE_DSC . '</p>' . $form->render();
        }

        // Only a webmaster may write a new module tree under modules/ (a module admin's rights
        // stop at their own module); the page normally sits behind cp_header(), but the shared
        // handler must not depend on that.
        if (!Webmaster::isCurrentUser()) {
            \redirect_header('clone.php', 3, \defined('_NOPERM') ? \_NOPERM : 'Permission denied.');
            exit;
        }
        // Fail closed: no security service means no token check is possible, so no clone.
        $security = self::runtimeGlobal('xoopsSecurity');
        if (!\is_object($security) || !\method_exists($security, 'check') || !$security->check()) {
            $errors = (\is_object($security) && \method_exists($security, 'getErrors')) ? \implode('<br>', $security->getErrors()) : '';
            \redirect_header('clone.php', 3, '' !== $errors ? $errors : 'Security token missing or invalid');
            exit;
        }
        $clone = \mb_strtolower(Request::getString('clone', '', 'POST'));
        $safe  = HtmlBuilder::escape($clone);
        if (!self::isValidDirname($clone)) {
            \redirect_header('clone.php', 3, \_CO_MTOOLS_CLONE_INVALIDNAME);
            exit;
        }
        $modulesRoot = \dirname($helper->path());
        if (\file_exists($modulesRoot . '/' . $clone)) {
            \redirect_header('clone.php', 3, \_CO_MTOOLS_CLONE_EXISTS);
            exit;
        }

        try {
            $targetPath = self::clone($helper->path(), $clone);
        } catch (\Throwable) {
            return \_CO_MTOOLS_CLONE_FAIL;
        }
        $message = \sprintf(
            \_CO_MTOOLS_CLONE_CONGRAT,
            "<a href='" . \XOOPS_URL . "/modules/system/admin.php?fct=modulesadmin'>" . \ucfirst($safe) . '</a>',
        ) . "<br>\n";
        if (!self::createLogo($targetPath)) {
            $message .= \_CO_MTOOLS_CLONE_IMAGEFAIL;
        }

        return $message;
    }

    /** @param list<string> $search @param list<string> $replace */
    private static function copyTree(string $source, string $target, array $search, array $replace, bool $topLevel): void
    {
        if (!@\mkdir($target) && !\is_dir($target)) {
            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $target));
        }
        $entries = \scandir($source, \SCANDIR_SORT_ASCENDING);
        if (false === $entries) {
            throw new \RuntimeException(\sprintf('Directory "%s" could not be read', $source));
        }
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry || ($topLevel && \in_array($entry, self::SKIPPED_ENTRIES, true))) {
                continue;
            }
            $from = $source . '/' . $entry;
            $to   = $target . '/' . \str_replace($search, $replace, $entry);
            if (PathContainment::isLink($from)) {
                // A link could point outside the module tree; a clone never dereferences it.
                continue;
            }
            if (\is_dir($from)) {
                self::copyTree($from, $to, $search, $replace, false);
                continue;
            }
            $extension = \mb_strtolower(\pathinfo($from, \PATHINFO_EXTENSION));
            if (\in_array($extension, self::BINARY_EXTENSIONS, true)) {
                if (!@\copy($from, $to)) {
                    throw new \RuntimeException(\sprintf('File "%s" could not be copied to "%s"', $from, $to));
                }
                continue;
            }
            $content = \file_get_contents($from);
            if (false === $content || false === @\file_put_contents($to, \str_replace($search, $replace, $content))) {
                throw new \RuntimeException(\sprintf('File "%s" could not be copied to "%s"', $from, $to));
            }
        }
    }

    /** @legacy-global-accessor */
    private static function runtimeGlobal(string $name): mixed
    {
        return $GLOBALS[$name] ?? null;
    }
}
