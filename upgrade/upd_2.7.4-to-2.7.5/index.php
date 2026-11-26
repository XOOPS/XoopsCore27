<?php

/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

use Xoops\Upgrade\UpgradeControl;
use Xoops\Upgrade\XoopsUpgrade;

/**
 * Upgrade from 2.7.4 to 2.7.5
 *
 * Tasks:
 *  1. datahtaccess — replace the Apache 2.2-only xoops_data/.htaccess with the form
 *                    that works on Apache 2.2 and 2.4.
 *  2. bootstrapassets — remove retired wizard and editor assets and stock Bootstrap theme files.
 *
 * Why: xoops_data/.htaccess shipped as "order deny,allow / deny from all". Apache 2.4
 * without mod_access_compat answers that with HTTP 500 instead of 403. It still refuses
 * the request, so nothing is exposed, but every request into xoops_data looks like a
 * server fault. xoops_lib/.htaccess had the same defect and was fixed in 2.7.4 (#253).
 *
 * Only a file that still holds exactly those two directives is rewritten (case, spacing
 * and blank lines aside). A missing file, a symbolic link or any other content is the
 * site's own choice and is left alone; a site may have relocated xoops_data or written
 * its own rules. Existing sites keep their xoops_data on upgrade, so a fixed copy in the
 * release package never reaches them; hence this task.
 *
 * UpgradeControl scans every "*-to-*" directory and asks each patch's check_ methods
 * whether it applies; applicability is not decided by comparing version numbers.
 *
 * @category     Upgrade
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package      XOOPS
 * @link         https://xoops.org
 * @since        2.7.5
 * @author       XOOPS Team
 */
class Upgrade_275 extends XoopsUpgrade
{
    /**
     * The xoops_data/.htaccess written by this task. htdocs/xoops_data/.htaccess ships
     * the same text; Upgrade275Test keeps the two identical.
     */
    public const DATA_HTACCESS = <<<'HTACCESS'
# xoops_data holds the site configuration, caches and logs. Nothing in it is meant to
# be requested over the web.
#
# Apache only -- on nginx or IIS these directives are ignored. The safest
# configuration by far is to place xoops_data OUTSIDE the document root,
# which makes all of this moot.

# Apache 2.4
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

# Apache 2.2
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>

HTACCESS;

    /** Session key set when the file could not be written and the admin was told. */
    private const SKIP_KEY = 'xoops_upgrade_275_datahtaccess_skipped';

    /** Paths relative to the deployed web root, including the uploaded upgrade folder. */
    private const OBSOLETE_BOOTSTRAP_FILES = [
        'install/assets/css/bootstrap.css',
        'install/assets/css/bootstrap.min.css',
        'install/assets/fonts/glyphicons-halflings-regular.eot',
        'install/assets/fonts/glyphicons-halflings-regular.svg',
        'install/assets/fonts/glyphicons-halflings-regular.ttf',
        'install/assets/fonts/glyphicons-halflings-regular.woff',
        'install/assets/fonts/glyphicons-halflings-regular.woff2',
        'install/assets/js/bootstrap.js',
        'install/assets/js/bootstrap.min.js',
        'upgrade/assets/css/bootstrap.css',
        'upgrade/assets/css/bootstrap.min.css',
        'upgrade/assets/fonts/glyphicons-halflings-regular.eot',
        'upgrade/assets/fonts/glyphicons-halflings-regular.svg',
        'upgrade/assets/fonts/glyphicons-halflings-regular.ttf',
        'upgrade/assets/fonts/glyphicons-halflings-regular.woff',
        'upgrade/assets/fonts/glyphicons-halflings-regular.woff2',
        'upgrade/assets/js/bootstrap.js',
        'upgrade/assets/js/bootstrap.min.js',
        'upgrade/assets/js/jquery.js',
        'upgrade/assets/js/xo-installer.js',
        'class/xoopseditor/tinymce5/js/tinymce/plugins/xoopsemoticons/css/bootstrap.min.css',
        'class/xoopseditor/tinymce5/js/tinymce/plugins/xoopsimagemanager/css/bootstrap.min.css',
        'class/xoopseditor/tinymce5/js/tinymce/plugins/xoopsimagemanager/js/bootstrap.bundle.min.js',
        'class/xoopseditor/tinymce7/js/tinymce/plugins/xoopsimagemanager/css/bootstrap.min.css',
        'class/xoopseditor/tinymce7/js/tinymce/plugins/xoopsimagemanager/js/bootstrap.bundle.min.js',
    ];

    /**
     * @param XoopsMySQLDatabase $db      database connection
     * @param UpgradeControl     $control upgrade control instance
     */
    public function __construct(XoopsMySQLDatabase $db, UpgradeControl $control)
    {
        parent::__construct($db, $control, basename(__DIR__));
        $this->tasks = ['datahtaccess', 'bootstrapassets'];
    }

    // =========================================================================
    // Task 1: datahtaccess
    // =========================================================================

    /**
     * Is xoops_data/.htaccess anything other than the legacy two-line file?
     *
     * A file that could not be written is also counted as done for the rest of the
     * session, as long as it is still the legacy file, so the upgrade can finish once
     * the administrator has been told to replace it.
     *
     * @return bool true when there is nothing to do
     */
    public function check_datahtaccess(): bool
    {
        $legacy = self::isLegacyFile(self::dataHtaccessPath());
        if (!empty($_SESSION[self::SKIP_KEY])) {
            if ($legacy) {
                return true;
            }
            unset($_SESSION[self::SKIP_KEY]);
        }

        return !$legacy;
    }

    /**
     * Rewrite a legacy xoops_data/.htaccess.
     *
     * @return bool true when the file was rewritten, needed no change, or could not be
     *              written and the administrator was told
     */
    public function apply_datahtaccess(): bool
    {
        $path = self::dataHtaccessPath();
        if (!self::isLegacyFile($path)) {
            return true;
        }

        $shown = htmlspecialchars($this->relativePath($path), ENT_QUOTES, 'UTF-8');
        if (!$this->replaceFile($path, self::DATA_HTACCESS)) {
            $this->logError(_XOOPS_UPGRADE_275_HTACCESS_NOT_WRITTEN, $shown);
            $_SESSION[self::SKIP_KEY] = true;

            return true;
        }
        $this->logSuccess(_XOOPS_UPGRADE_275_HTACCESS_UPDATED, $shown);

        return true;
    }

    /**
     * Replace a file's content without ever leaving it empty or half-written.
     *
     * The text goes to a sibling temporary file first, which is checked, given the
     * original's permissions and then renamed over the original. A failed or short
     * write leaves the original untouched: an .htaccess cut off mid-write would stop
     * protecting the folder and no longer look like the legacy file, so it would never
     * be retried.
     *
     * @param string $path    file to replace
     * @param string $content new content
     *
     * @return bool true when the file now holds exactly $content
     */
    protected function replaceFile(string $path, string $content): bool
    {
        $temp = $path . '.tmp-' . bin2hex(random_bytes(4));
        set_error_handler(static fn (): bool => true);
        try {
            $replaced = $this->writeFile($temp, $content) === strlen($content)
                && chmod($temp, fileperms($path) & 0777)
                && rename($temp, $path);
            if (!$replaced && file_exists($temp)) {
                unlink($temp);
            }
        } finally {
            restore_error_handler();
        }

        return $replaced;
    }

    /**
     * Write a file; the seam the tests use to simulate a failing disk.
     *
     * @param string $path    file to write
     * @param string $content content
     *
     * @return int|false bytes written, or false
     */
    protected function writeFile(string $path, string $content): int|false
    {
        return file_put_contents($path, $content, LOCK_EX);
    }

    /**
     * @return string absolute path of xoops_data/.htaccess
     */
    private static function dataHtaccessPath(): string
    {
        return rtrim(XOOPS_VAR_PATH, '/\\') . '/.htaccess';
    }

    /**
     * Does the file hold exactly the two legacy directives?
     *
     * Apache reads directives case-insensitively, so case, surrounding spaces, runs of
     * spaces, line endings, blank lines and a byte-order mark do not matter. Anything
     * else, including a comment, makes it the site's own file. A symbolic link or an
     * unreadable file is never treated as legacy.
     *
     * @param string $path file to inspect
     *
     * @return bool
     */
    private static function isLegacyFile(string $path): bool
    {
        if (!is_file($path) || is_link($path)) {
            return false;
        }
        $content = self::readCandidateFile($path);
        if (!is_string($content)) {
            return false;
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $lines = [];
        foreach (preg_split('/\R/', strtolower($content)) as $line) {
            $line = trim((string) preg_replace('/[ \t]+/', ' ', $line));
            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return $lines === ['order deny,allow', 'deny from all'];
    }

    /** @return \Generator<string, string> relative name => absolute filename */
    private function obsoleteBootstrapFiles(): \Generator
    {
        $root = realpath(XOOPS_ROOT_PATH);
        if (false === $root) {
            throw new \RuntimeException(sprintf(_FILE_ACCESS_ERROR, 'XOOPS_ROOT_PATH'));
        }
        $themeHashes = require __DIR__ . '/bootstrap-theme-hashes.php';
        foreach (array_fill_keys(self::OBSOLETE_BOOTSTRAP_FILES, null) + $themeHashes as $relative => $hashes) {
            $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $dir = dirname($path);
            $actual = realpath($dir);
            if (false === $actual) {
                continue;
            }
            // A linked parent can lead to another site's assets, even inside the same web root.
            $sameDirectory = PHP_OS_FAMILY === 'Windows' ? strcasecmp($actual, $dir) === 0 : $actual === $dir;
            $file = $actual . DIRECTORY_SEPARATOR . basename($path);
            if (!$sameDirectory || (!is_file($file) && !is_link($file))) {
                continue;
            }
            if (null === $hashes || self::isStockThemeFile($file, $hashes)) {
                yield $relative => $file;
            }
        }
    }

    /** @param string[] $hashes stock SHA-256 digests for this exact theme path */
    private static function isStockThemeFile(string $file, array $hashes): bool
    {
        if (is_link($file) || !is_file($file)) {
            return false;
        }
        // Every stock file fits in 1 MiB, including CRLF checkouts. Bound reads of custom files.
        $limit = 1048576;
        $content = self::readCandidateFile($file, $limit + 1);
        return false !== $content && strlen($content) <= $limit
            && in_array(hash('sha256', str_replace("\r\n", "\n", $content)), $hashes, true);
    }

    /** A failed read leaves the file untouched and keeps absolute paths off the upgrade page. */
    private static function readCandidateFile(string $path, ?int $length = null): string|false
    {
        set_error_handler(static fn (): bool => true, E_WARNING);
        try {
            return file_get_contents($path, false, null, 0, $length);
        } finally {
            restore_error_handler();
        }
    }

    public function check_bootstrapassets(): bool
    {
        return !$this->obsoleteBootstrapFiles()->valid();
    }

    public function apply_bootstrapassets(): bool
    {
        $ok = true;
        foreach ($this->obsoleteBootstrapFiles() as $relative => $path) {
            if (!$this->removeLeftover($path)) {
                $message = defined('_XOOPS_UPGRADE_275_ASSET_NOT_REMOVED')
                    ? _XOOPS_UPGRADE_275_ASSET_NOT_REMOVED
                    : 'Could not remove obsolete asset %s. Remove it manually or correct its directory permissions and retry.';
                $this->logError($message, $relative);
                $ok = false;
            }
        }
        return $ok;
    }
}

return Upgrade_275::class;
