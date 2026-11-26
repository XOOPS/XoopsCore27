<?php
defined('XOOPS_ROOT_PATH') or die();


?>
<!doctype html>
<html lang="<?php echo _LANGCODE; ?>">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title><?php echo XOOPS_VERSION . ' : ' . _XOOPS_UPGRADE; ?></title>

    <link rel="icon" type="image/png" href="<?php echo XOOPS_URL ?>/upgrade/assets/img/favicon.png"/>
    <!-- Bootstrap Core CSS -->
    <link href="../media/bootstrap5/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">

    <!-- Custom Fonts -->
    <link href="../media/font-awesome6/css/fontawesome.min.css" rel="stylesheet" type="text/css" as="font" crossorigin="anonymous">
    <link href="../media/font-awesome6/css/solid.min.css" rel="stylesheet" as="font" crossorigin="anonymous">
    <link href="../media/font-awesome6/css/brands.min.css" rel="stylesheet" as="font" crossorigin="anonymous">
    <link href="../media/font-awesome6/css/v4-shims.min.css" rel="stylesheet" as="font" crossorigin="anonymous">

    <?php
    if (!empty($extraSources)) {
        echo $extraSources;
    }
?>
    <?php
if (file_exists('language/' . $viewModel['upgradeLanguage'] . '/style.css')) {
    echo '<link rel="stylesheet" type="text/css" media="all" href="language/'
        . htmlspecialchars((string) $viewModel['upgradeLanguage'], ENT_QUOTES, 'UTF-8') . '/style.css" />';
}
?>

</head>

<body>
<div class="wrapper">

    <nav class="navbar navbar-expand-lg bg-dark sticky-top" data-bs-theme="dark">
        <div class="container-fluid">
            <span class="navbar-brand"><img src="assets/img/logo_small.png" alt="XOOPS"></span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#wizard-nav"
                    aria-controls="wizard-nav" aria-expanded="false" aria-label="<?php echo htmlspecialchars(defined('_TOGGLE_NAVIGATION') ? _TOGGLE_NAVIGATION : 'Toggle navigation', ENT_QUOTES, 'UTF-8'); ?>">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div id="wizard-nav" class="collapse navbar-collapse">
                <ul class="navbar-nav flex-column side-nav">
                    <?php
                    $firstNeeded = true;
                    foreach ($viewModel['upgradeQueue'] as $stepName => $info) {
                        if (!$info->applied && $firstNeeded) {
                            echo '<li class="nav-item"><span class="nav-link active" aria-current="step"><span class="fa-solid fa-exclamation-triangle"></span> '
                                . htmlspecialchars((string) $stepName, ENT_QUOTES, 'UTF-8') . '</span></li>';
                            $firstNeeded = false;
                        } elseif (!$info->applied) {
                            echo '<li class="nav-item"><span class="nav-link"><span class="fa-solid fa-exclamation-triangle text-warning"></span> '
                                . htmlspecialchars((string) $stepName, ENT_QUOTES, 'UTF-8') . '</span></li>';
                        } else {
                            echo '<li class="nav-item"><span class="nav-link"><span class="fa-solid fa-check text-success"></span> '
                                . htmlspecialchars((string) $stepName, ENT_QUOTES, 'UTF-8') . '</span></li>';
                        }
                    }
                    ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <button type="button" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="<?php echo htmlspecialchars(_LANGUAGE, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-lg fa-language"></i><span class="visually-hidden"><?php echo _LANGUAGE; ?></span></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach ($viewModel['languages'] as $lang): ?>
                                <li><a class="dropdown-item" href="?lang=<?php echo rawurlencode((string) $lang); ?>"><?php echo htmlspecialchars((string) $lang, ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <button type="button" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-book"></i> <?php echo _SUPPORT; ?></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php
                            $allSupport = [];
                            foreach ($viewModel['supportSites'] as $sites) {
                                if (is_array($sites)) {
                                    $allSupport = array_merge($allSupport, $sites);
                                }
                            }
                            foreach ($allSupport as $support) {
                                echo '<li><a class="dropdown-item" href="' . htmlspecialchars((string) $support['url'], ENT_QUOTES, 'UTF-8')
                                    . '" target="_blank" rel="noopener noreferrer">'
                                    . htmlspecialchars((string) $support['title'], ENT_QUOTES, 'UTF-8') . '</a></li>';
                            }
                            ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://github.com/XOOPS/XoopsCore27" target="_blank" rel="noopener noreferrer" title="<?php echo htmlspecialchars(_XOOPS_SOURCE_CODE, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-brands fa-lg fa-github"></i></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="content-wrapper">

        <div class="container-fluid">
            <div class="row g-3 mb-4">
                <?php if (!$viewModel['preflightDone']) { ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-danger h-100">
                        <div class="card-body text-bg-danger">
                            <div class="row">
                                <div class="col-3">
                                    <span class="fa-solid fa-hand fa-2x"></span>
                                </div>
                                <div class="col-9 text-end">
                                    <div class="fs-2">Smarty4</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-primary">
                            <?php echo _XOOPS_SMARTY4_MIGRATION; ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <?php if ($viewModel['hasError']) { ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-danger h-100">
                        <div class="card-body text-bg-danger">
                            <div class="row">
                                <div class="col-3">
                                    <span class="fa-solid fa-hand fa-2x"></span>
                                </div>
                                <div class="col-9 text-end">
                                    <div class="fs-2"><span class="fa-solid fa-ban"></span></div>
                                    <div><?php echo XOOPS_ERROR_ENCOUNTERED; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <?php echo XOOPS_ERROR_SEE_BELOW; ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-primary h-100">
                        <div class="card-body text-bg-primary">
                            <div class="row">
                                <div class="col-3">
                                    <span class="fa-solid fa-gauge fa-2x"></span>
                                </div>
                                <div class="col-9 text-end">
                                    <div class="fs-2"><?php echo $viewModel['patchCount']; ?></div>
                                    <div><?php echo _PATCH_COUNT; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-primary">
                            <?php echo _XOOPS_UPGRADE; ?>
                        </div>
                    </div>
                </div>
                <?php
$versionParts = [];
$versionResult = preg_match('/(^[a-z\s]*)([0-9\.]*)/i', XOOPS_VERSION, $versionParts);
?>

                <div class="col-lg-3 col-md-6">
                    <div class="card border-success h-100">
                        <div class="card-body text-bg-success">
                            <div class="row">
                                <div class="col-3">
                                    <i class="fa-solid fa-tag fa-2x"></i>
                                </div>
                                <div class="col-9 text-end">
                                    <div class="fs-2"><?php echo $versionParts[2]; ?></div>
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

            <div id="wizard">
                <?php echo $viewModel['content']; ?>
            </div>
        </div>
    </main>
</div>
<script src="../media/bootstrap5/js/bootstrap.bundle.min.js"></script>
</body>
</html>
