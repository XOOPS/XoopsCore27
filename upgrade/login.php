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

defined('XOOPS_ROOT_PATH') or exit();

$uname = trim(\Xmf\Request::getString('uname', '', 'POST'));
$pass = trim((string) \Xmf\Request::getVar('pass', '', 'POST'));

if ('' === $uname || '' === $pass) {
    ?>
    <h2><?php echo _USER_LOGIN; ?></h2>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES | ENT_HTML5); ?>" method="post">
        <label for="uname"><?php echo _USERNAME; ?></label>
        <div class="input-group">
            <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
            <input class="form-control" type="text" name="uname" id="uname" value="" placeholder="<?php echo _USERNAME_PLACEHOLDER; ?>" autocomplete="current-password">
        </div>

        <label for="pass"><?php echo _PASSWORD; ?></label>
        <div class="input-group">
            <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
            <input class="form-control" type="password" name="pass" id="pass" placeholder="<?php echo _PASSWORD_PLACEHOLDER; ?>">
        </div>
        <div class="input-group">
            <br>
            <button type="submit" class="btn btn-default"><?php echo _LOGIN; ?></button>
        </div>
    </form>
    <?php
} else {
    $member_handler = xoops_getHandler('member');

    include_once XOOPS_ROOT_PATH . '/class/auth/authfactory.php';
    if (!@include_once XOOPS_ROOT_PATH . '/language/' . $upgrade_language . '/auth.php') {
        include_once XOOPS_ROOT_PATH . '/language/english/auth.php';
    }
    $xoopsAuth = XoopsAuthFactory::getAuthConnection($uname);
    $user      = $xoopsAuth->authenticate($uname, $pass);

    // For XOOPS 2.2*
    if (!is_object($user)) {
        try {
            $criteria = new CriteriaCompo(new Criteria('loginname', $uname));
            $criteria->add(new Criteria('pass', md5($pass)));
            [$user] = $member_handler->getUsers($criteria);
        } catch (\Throwable $e) {
            $user = false;
        }
    }

    $isAllowed = false;
    if (is_object($user) && $user->getVar('level') > 0) {
        $isAllowed = true;
        if ($xoopsConfig['closesite'] == 1) {
            $groups = $user->getGroups();
            if (in_array(XOOPS_GROUP_ADMIN, $groups) || array_intersect($groups, $xoopsConfig['closesite_okgrp'])) {
                $isAllowed = true;
            } else {
                $isAllowed = false;
            }
        }
    }
    if ($isAllowed) {
        $user->setVar('last_login', time());
        if (!$member_handler->insertUser($user)) {
            $errors = method_exists($user, 'getErrors') ? $user->getErrors() : [];
            $errorText = is_array($errors) ? implode('; ', $errors) : (string) $errors;
            trigger_error(
                sprintf(
                    'insertUser failed for uid %d during upgrade login%s',
                    (int) $user->getVar('uid'),
                    '' !== $errorText ? ': ' . $errorText : ''
                ),
                E_USER_WARNING
            );
        }
        // Regenerate a new session id and destroy old session
        $GLOBALS['sess_handler']->regenerate_id(true);
        $_SESSION                    = [];
        $_SESSION['xoopsUserId']     = $user->getVar('uid');
        $_SESSION['xoopsUserGroups'] = $user->getGroups();
        $user_theme                  = $user->getVar('theme');
        if (in_array($user_theme, $xoopsConfig['theme_set_allowed'])) {
            $_SESSION['xoopsUserTheme'] = $user_theme;
        }
    }

    header('location: ' . XOOPS_URL . '/upgrade/index.php');
    exit();
}
