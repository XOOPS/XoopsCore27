<?php
/**
 * Messages logged by the 2.7.4 to 2.7.5 upgrade patch (upd_2.7.4-to-2.7.5/index.php).
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

// Task: datahtaccess; %s is the file, e.g. xoops_data/.htaccess
defined('_XOOPS_UPGRADE_275_HTACCESS_UPDATED') || define('_XOOPS_UPGRADE_275_HTACCESS_UPDATED', '%s was updated so that Apache 2.4 denies access to it as Apache 2.2 does');
defined('_XOOPS_UPGRADE_275_HTACCESS_NOT_WRITTEN') || define('_XOOPS_UPGRADE_275_HTACCESS_NOT_WRITTEN', '%s could not be written. It still uses Apache 2.2 syntax, which Apache 2.4 answers with a server error instead of denying access. Replace it with xoops_data/.htaccess from this release.');
defined('_XOOPS_UPGRADE_275_ASSET_NOT_REMOVED') || define('_XOOPS_UPGRADE_275_ASSET_NOT_REMOVED', 'Could not remove obsolete asset %s. Remove it manually or correct its directory permissions and retry.');
defined('_XOOPS_UPGRADE_275_ASSET_UNREADABLE') || define('_XOOPS_UPGRADE_275_ASSET_UNREADABLE', 'Could not inspect %s. Check file and directory permissions and retry.');
defined('_XOOPS_UPGRADE_275_ARCHIVE_BUTTON') || define('_XOOPS_UPGRADE_275_ARCHIVE_BUTTON', 'Archive unused theme Bootstrap files');
defined('_XOOPS_UPGRADE_275_ARCHIVE_HINT') || define('_XOOPS_UPGRADE_275_ARCHIVE_HINT', 'Optional cleanup for xBootstrap5 and xSwatch5. Choose this only if your templates and custom pages no longer load Bootstrap from their theme folders. Recognized stock files will move into a recovery folder under xoops_data. Continue to keep them in place.');
defined('_XOOPS_UPGRADE_275_ARCHIVE_LOCATION') || define('_XOOPS_UPGRADE_275_ARCHIVE_LOCATION', 'Recovery folder: %s. To restore an archived file, copy it back to its matching theme path.');
defined('_XOOPS_UPGRADE_275_ARCHIVE_UNAVAILABLE') || define('_XOOPS_UPGRADE_275_ARCHIVE_UNAVAILABLE', 'Could not safely archive %s. Check that xoops_data and the theme directory are writable and support hard links on the same filesystem.');
defined('_XOOPS_UPGRADE_275_ARCHIVE_RECOVER') || define('_XOOPS_UPGRADE_275_ARCHIVE_RECOVER', 'Cleanup of %s could not be completed. Review the preserved entry at %s before restoring it manually.');
