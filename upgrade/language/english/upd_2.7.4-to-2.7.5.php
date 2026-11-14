<?php
/**
 * Messages logged by the 2.7.4 to 2.7.5 upgrade patch (upd_2.7.4-to-2.7.5/index.php).
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */

// Task: datahtaccess; %s is the file, e.g. xoops_data/.htaccess
define('_XOOPS_UPGRADE_275_HTACCESS_UPDATED', '%s was updated so that Apache 2.4 denies access to it as Apache 2.2 does');
define('_XOOPS_UPGRADE_275_HTACCESS_NOT_WRITTEN', '%s could not be written. It still uses Apache 2.2 syntax, which Apache 2.4 answers with a server error instead of denying access. Replace it with xoops_data/.htaccess from this release.');
