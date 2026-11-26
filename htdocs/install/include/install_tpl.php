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
 * Installer template file
 *
 * This template was derived in part from sb-admin, a free, open source, Bootstrap
 * admin theme created by Start Bootstrap, made available under an MIT license.
 * See: https://github.com/BlackrockDigital/startbootstrap-sb-admin
 *
 * @copyright    (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package          installer
 * @since            2.3.0
 * @author           Haruki Setoyama  <haruki@planewave.org>
 * @author           Kazumi Ono <webmaster@myweb.ne.jp>
 * @author           Skalpa Keo <skalpa@xoops.org>
 * @author           Taiwen Jiang <phppp@users.sourceforge.net>
 * @author           Kris <kris@frxoops.org>
 * @author           DuGris (aka L. JEN) <dugris@frxoops.org>
 * @author           Mamba
 **/

defined('XOOPS_INSTALL') || die('XOOPS Installation wizard die');

include_once __DIR__ . '/../../language/' . $wizard->language . '/global.php';

$versionParts = [];
preg_match('/(^[a-z\s]*)([0-9\.]*)/i', XOOPS_VERSION, $versionParts);
?>

<!doctype html>
<html lang="<?php echo _LANGCODE; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo XOOPS_VERSION . ' : ' . XOOPS_INSTALL_WIZARD; ?> (<?php echo ($wizard->pageIndex + 1) . '/' . count($wizard->pages); ?>)</title>

    <!-- Bootstrap CSS -->
    <link href="../media/bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="../media/font-awesome6/css/fontawesome.min.css" rel="stylesheet" as="font" crossorigin="anonymous">
    <link href="../media/font-awesome6/css/solid.min.css" rel="stylesheet" as="font" crossorigin="anonymous">
    <link href="../media/font-awesome6/css/brands.min.css" rel="stylesheet" as="font" crossorigin="anonymous">
    <link href="../media/font-awesome6/css/v4-shims.min.css" rel="stylesheet" as="font" crossorigin="anonymous">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <?php if (file_exists('language/' . $wizard->language . '/style.css')): ?>
        <link rel="stylesheet" href="language/<?php echo $wizard->language; ?>/style.css">
    <?php endif; ?>
</head>

<body>
<div class="wrapper">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg bg-dark sticky-top" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><img src="assets/img/logo_small.png" alt="XOOPS"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="<?php echo htmlspecialchars(defined('TOGGLE_NAVIGATION') ? TOGGLE_NAVIGATION : 'Toggle navigation', ENT_QUOTES, 'UTF-8'); ?>">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Left sidebar -->
                <ul class="navbar-nav flex-column side-nav me-auto">
                    <?php foreach (array_keys($wizard->pages) as $k => $page): ?>
                        <li class="nav-item">
                            <?php if ($k == $wizard->pageIndex): ?>
                                <span class="nav-link active" aria-current="step"><i class="<?php echo str_replace('fa fa-', 'fa-solid fa-', $wizard->pages[$page]['icon']); ?>"></i> <?php echo $wizard->pages[$page]['name']; ?></span>
                            <?php elseif ($k > $wizard->pageIndex): ?>
                                <span class="nav-link disabled" aria-disabled="true"><i class="<?php echo str_replace('fa fa-', 'fa-solid fa-', $wizard->pages[$page]['icon']); ?>"></i> <?php echo $wizard->pages[$page]['name']; ?></span>
                            <?php else: ?>
                                <a class="nav-link" href="<?php echo htmlspecialchars($wizard->pageURI($page), ENT_QUOTES | ENT_HTML5); ?>"><i class="<?php echo str_replace('fa fa-', 'fa-solid fa-', $wizard->pages[$page]['icon']); ?> text-success"></i> <?php echo $wizard->pages[$page]['name']; ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Right menu -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false" href="#"><i class="fa-solid fa-book"></i> <?php echo SUPPORT; ?></a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php
                            $supportFile = __DIR__ . '/../language/' . $wizard->language . '/support.php';
                            if (file_exists($supportFile)) {
                                $supports =  include $supportFile;
                            }
                            if (isset($supports) && is_array($supports)) { foreach ($supports as $support): ?>
                                <li><a class="dropdown-item" href="<?php echo htmlspecialchars($support['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($support['title'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <?php endforeach; } ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://github.com/XOOPS/XoopsCore27" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars(XOOPS_SOURCE_CODE, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fa-brands fa-github fa-lg"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="content-wrapper">
        <div class="container-fluid">
            <!-- Status Cards -->
            <div class="row g-4 mb-4">
                <?php if (!empty($error)): ?>
                    <div class="col-lg-4">
                        <div class="card border-danger">
                            <div class="card-body bg-danger text-white">
                                <div class="row">
                                    <div class="col-3">
                                        <i class="<?php echo str_replace('fa fa-', 'fa-solid fa-', $wizard->pages[$wizard->currentPage]['icon']); ?> fa-3x"></i>
                                    </div>
                                    <div class="col-9 text-end">
                                        <div class="display-6"><i class="fa-solid fa-ban"></i></div>
                                        <div><?php echo XOOPS_ERROR_ENCOUNTERED; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Progress Card -->
                <div class="col-lg-4">
                    <div class="card border-primary">
                        <div class="card-body bg-primary text-white">
                            <div class="row">
                                <div class="col-3">
                                    <i class="<?php echo str_replace('fa fa-', 'fa-solid fa-', $wizard->pages[$wizard->currentPage]['icon']); ?> fa-3x"></i>
                                </div>
                                <div class="col-9 text-end">
                                    <div class="display-6"><?php echo ($wizard->pageIndex + 1) . '/' . count($wizard->pages); ?></div>
                                    <div><?php echo XOOPS_INSTALLING; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-primary">
                            <?php echo $wizard->pages[$wizard->currentPage]['name']; ?>
                        </div>
                    </div>
                </div>

                <!-- Version Card -->
                <div class="col-lg-4">
                    <div class="card border-success">
                        <div class="card-body bg-success text-white">
                            <div class="row">
                                <div class="col-3">
                                    <i class="fa-solid fa-tag fa-3x"></i>
                                </div>
                                <div class="col-9 text-end">
                                    <div class="display-6"><?php echo $versionParts[2]; ?></div>
                                    <div>Version</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-success">
                            <?php echo XOOPS_VERSION; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wizard Form -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form id="<?php echo $wizard->pages[$wizard->currentPage]['name']; ?>" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES | ENT_HTML5); ?>" method="post">
                                <h2><?php echo $wizard->pages[$wizard->currentPage]['title']; ?></h2>
                                <?php if (!empty($pageHasHelp) && str_contains($content, 'xoform-help')): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" data-bs-toggle="collapse" data-bs-target=".xoform-help" aria-expanded="false"><?php echo installerHtmlSpecialChars(defined('SHOW_HIDE_HELP') ? SHOW_HIDE_HELP : 'Show/hide help text'); ?></button>
                                <?php endif; ?>
                                <?php echo $content; ?>

                                <div class="text-end mt-4">
                                    <?php if (!empty($blockNext)): ?>
                                        <button class="btn btn-lg btn-secondary disabled" type="button" disabled aria-disabled="true">
                                            <?php echo BUTTON_NEXT; ?> <i class="fa-solid fa-caret-right"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-lg btn-success" type="<?php echo !empty($pageHasForm) ? 'submit' : 'button'; ?>"
                                                <?php if (empty($pageHasForm)): ?>onclick='location.href=<?php echo htmlspecialchars(json_encode($wizard->pageURI('+1'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES); ?>'<?php endif; ?>>
                                            <?php echo BUTTON_NEXT; ?> <i class="fa-solid fa-caret-right"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="assets/js/jquery.min.js"></script>
<script src="../media/bootstrap5/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($extraSources)) { echo $extraSources; } ?>
<script src="assets/js/xo-installer.js"></script>
<script type="text/javascript">
    $(document).ready(function () {
        /**
         * Check the url to see if we reached 'page_end.php' and if so, launch the cleanup via ajax.
         **/
        if ('page_end.php' == location.pathname.substring(location.pathname.lastIndexOf('/') + 1)) {
            var cleanupUrl = <?php echo json_encode(isset($cleanupUrl) ? $cleanupUrl : 'cleanup.php'); ?>;
            var instSuffix = <?php echo json_encode(isset($install_rename_suffix) ? $install_rename_suffix : ''); ?>;
            $.post(cleanupUrl, { instsuffix: instSuffix })
                .fail(function() {
                    console.warn('Install cleanup request failed. You may need to manually rename or remove the install directory.');
                });
        }
    });
</script>
</body>
</html>
