<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

/**
 * XOOPS Upgrade wizard.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @since     2.3.0
 * @author    Skalpa Keo <skalpa@xoops.org>
 * @author    Taiwen Jiang <phppp@users.sourceforge.net>
 * @author    XOOPS Development Team
 */

require_once __DIR__ . '/class/fatal_error_handler.php';

/*
 * Before xoops 2.5.8 the table 'sess_ip' was of type varchar (15). This is a problem for IPv6
 * addresses because it is longer. The upgrade process would change the column to VARCHAR(45)
 * but it requires login, which is failing. If the user has an IPv6 address, it is converted to
 * short IP during the upgrade. At the end of the upgrade IPV6 works
 *
 * Here we save the current IP address if needed
 */
$ip = $_SERVER['REMOTE_ADDR'];
if (strlen($_SERVER['REMOTE_ADDR']) > 15) {
    //new IP for upgrade
    $_SERVER['REMOTE_ADDR'] = '::1';
}

include_once __DIR__ . '/checkmainfile.php';
defined('XOOPS_ROOT_PATH') or die('Bad installation: please add this folder to the XOOPS install you want to upgrade');

if (!isset($_SESSION['preflight']) || (isset($_SESSION['preflight']) && $_SESSION['preflight'] !== 'complete')) {
    $_SESSION['preflight'] = 'active';
    header('Location: ./preflight.php');
    exit;
}

$reporting = 0;
if (isset($_GET['debug'])) {
    $reporting = -1;
}
error_reporting($reporting);
$xoopsLogger->activated = true;
$xoopsLogger->enableRendering();
xoops_loadLanguage('logger');
set_exception_handler('fatalPhpErrorHandler'); // should have been changed by now, reset to ours

require_once __DIR__ . '/class/autoload.php';

$error = false;
$upgradeControl = new \Xoops\Upgrade\UpgradeControl($GLOBALS['xoopsDB']);

// Determine language FIRST (loads 'upgrade' language internally)
$upgradeControl->determineLanguage();

// Then load additional language files
$languageRoot = realpath(__DIR__ . '/language');
$language = $upgradeControl->normalizeLanguage($upgradeControl->upgradeLanguage);
$userFile = false !== $languageRoot
    ? realpath($languageRoot . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . 'user.php')
    : false;
if (
    false !== $languageRoot
    && false !== $userFile
    && str_starts_with($userFile, $languageRoot . DIRECTORY_SEPARATOR)
) {
    include_once $userFile;
} else {
    include_once XOOPS_ROOT_PATH . '/language/english/user.php';
}
$upgradeControl->loadLanguage('smarty4');

$upgradeControl->storeMainfileCheck($needMainfileRewrite, $mainfileKeys);
$upgradeControl->buildUpgradeQueue();

ob_start();
global $xoopsUser;
if (!xoops_upgrade_user_is_webmaster($xoopsUser)) {
    include_once __DIR__ . '/login.php';
} else {
    // Always clear compiled templates and caches when a webmaster opens the
    // wizard. This used to be a patch task gated on $_SESSION['cache-cleaned'],
    // which is absent at the start of every new session, so 2.5.10-to-2.5.11
    // (and later 2.5.11-to-2.7.0) re-queued on every run.
    if (empty($_SESSION['upgrade_cache_cleaned']) && $upgradeControl->cleanCaches()) {
        $_SESSION['upgrade_cache_cleaned'] = true;
    }
    // Self-contained CSRF token for the schema-mutating apply() path, mirroring
    // preflight.php's per-session token ($xoopsSecurity is unavailable this early
    // when upgrading a possibly pre-2.7 site). Read the action from POST only and
    // require a matching token before any patch is applied.
    if (empty($_SESSION['preflight_csrf'])) {
        $_SESSION['preflight_csrf'] = bin2hex(random_bytes(32));
    }
    $op = Xmf\Request::getCmd('action', '', 'POST');
    if ('' !== $op) {
        $sentToken = Xmf\Request::getString('upgrade_token', '', 'POST');
        if (!hash_equals((string) $_SESSION['preflight_csrf'], $sentToken)) {
            $op = '';
        }
    }
    if (
        (!$upgradeControl->needUpgrade && 'archivethemes' !== $op)
        || ($upgradeControl->needUpgrade && 'next' !== $op)
    ) {
        $op = '';
    }
    // Patches change the database and files in place and cannot be rolled back
    // from here. The first apply of a session needs the webmaster to confirm,
    // on the welcome form, that a current backup exists.
    $backupMissing = false;
    if ('' !== $op && empty($_SESSION['upgrade_backup_confirmed'])) {
        if (1 === Xmf\Request::getInt('confirmed', 0, 'POST')) {
            $_SESSION['upgrade_backup_confirmed'] = true;
        } else {
            $op            = '';
            $backupMissing = true;
        }
    }
    if (empty($op)) {
        $upgradeControl->loadLanguage('welcome');
        // A translated welcome.php older than 2.7.5 lacks the backup texts.
        defined('_XOOPS_UPGRADE_BACKUP_CONFIRM') || define('_XOOPS_UPGRADE_BACKUP_CONFIRM', 'I have a current backup of the database, mainfile.php and the xoops_data folder, and I know how to restore it.');
        defined('_XOOPS_UPGRADE_BACKUP_REQUIRED') || define('_XOOPS_UPGRADE_BACKUP_REQUIRED', 'The patches change the database and files in place and cannot be undone from here. Make a backup first, then tick the box below to continue.');
        echo _XOOPS_UPGRADE_WELCOME;
        if ($backupMissing) {
            echo '<div class="alert alert-danger">' . _XOOPS_UPGRADE_BACKUP_REQUIRED . '</div>';
        }
    } elseif ('next' === $op) {
        if (!empty($upgradeControl->needWriteFiles)) {
            echo '<div class="card border-danger mb-3">'
                . '<div class="card-header">' . _SET_FILES_WRITABLE . '</div>'
                . '<div class="card-body"><ul class="fa-ul">';
            foreach ($upgradeControl->needWriteFiles as $file) {
                echo '<li><i class="fa-li fa-solid fa-ban text-danger"></i>' . $file . '</li>';
                $error = true;
            }
            echo '</ul></div></div>';
        } elseif (false !== ($next = $upgradeControl->getNextPatch())) {
            // getNextPatch() is false once every patch is applied; the empty queue
            // then falls through to the continue form below.
            /** @var XoopsUpgrade $upgrader */
            $upgrader = $upgradeControl->upgradeQueue[$next]->getPatch();
            $res = $upgrader->apply();
            // Report the outcome, not the intent: the heading used to be printed
            // before apply() ran, so a patch that never took still read "Applied".
            printf('<h2>' . ($res ? _PERFORMING_UPGRADE : _FAILED_PATCH) . '</h2>', $next);
            if ($message = $upgrader->message()) {
                echo '<div class="bg-body-tertiary border rounded p-3 mb-3">' . $message . '</div>';
            }

            if ($res) {
                $upgradeControl->markApplied($next);
            } else {
                $error = true;
            }
        }
    }
    if (0 === $upgradeControl->countUpgradeQueue()) {
        echo $upgradeControl->oneButtonContinueForm(
            XOOPS_URL . '/modules/system/admin.php?fct=modulesadmin&op=update&module=system',
            [],
        );
        $themePatch = ($upgradeControl->upgradeQueue['upd_2.7.4-to-2.7.5'] ?? null)?->getPatch();
        if ($themePatch instanceof Upgrade_275) {
            if ('archivethemes' === $op) {
                $error = !$themePatch->archiveThemeAssets();
                echo '<p>' . $themePatch->message() . '</p>';
            }
            if ($themePatch->hasThemeAssets()) {
                echo '<p class="mt-4">' . htmlspecialchars(_XOOPS_UPGRADE_275_ARCHIVE_HINT, ENT_QUOTES, 'UTF-8') . '</p>';
                echo $upgradeControl->oneButtonContinueForm(
                    'index.php',
                    ['action' => 'archivethemes', 'upgrade_token' => (string) $_SESSION['preflight_csrf']],
                    empty($_SESSION['upgrade_backup_confirmed']) ? _XOOPS_UPGRADE_BACKUP_CONFIRM : '',
                    _XOOPS_UPGRADE_275_ARCHIVE_BUTTON,
                );
            }
        }
    } else {
        echo $upgradeControl->oneButtonContinueForm(
            'index.php',
            ['action' => 'next', 'upgrade_token' => (string) ($_SESSION['preflight_csrf'] ?? '')],
            empty($_SESSION['upgrade_backup_confirmed']) ? _XOOPS_UPGRADE_BACKUP_CONFIRM : '',
        );
    }
}
$content = ob_get_contents();
ob_end_clean();

// Pre-compute support data for all languages
$allSupportSites = [];
foreach ($upgradeControl->availableLanguages() as $lang) {
    $upgradeControl->supportSites = [];
    $upgradeControl->loadLanguage('support', $lang);
    $allSupportSites[$lang] = $upgradeControl->supportSites;
}

$viewModel = [
    'content'         => $content,
    'upgradeQueue'    => $upgradeControl->upgradeQueue,
    'upgradeLanguage' => $upgradeControl->upgradeLanguage,
    'patchCount'      => $upgradeControl->countUpgradeQueue(),
    'hasError'        => $error,
    'preflightDone'   => ($_SESSION['preflight'] ?? '') === 'complete',
    'languages'       => $upgradeControl->availableLanguages(),
    'supportSites'    => $allSupportSites,
];

include_once __DIR__ . '/upgrade_tpl.php';
