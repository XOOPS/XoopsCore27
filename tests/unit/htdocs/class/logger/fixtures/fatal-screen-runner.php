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

declare(strict_types=1);

/**
 * Runs XoopsLogger's fatal path in its own process, because that path ends in exit().
 *
 * Usage: php fatal-screen-runner.php <guest|member|developer|throwing> <error|exception>
 *
 * Prints the page the visitor would receive, then a line "--RESULT--" and a JSON object
 * with the HTTP status and the messages the registered logger recorded.
 *
 * @category  Xoops
 * @package   Logger
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

define('XOOPS_ROOT_PATH', dirname(__DIR__, 6) . '/htdocs');

$requester = $argv[1] ?? 'guest';
$entry     = $argv[2] ?? 'error';

// 'guest' leaves the helper undefined: an early-bootstrap fatal, before debugconfig.php.
if ('member' === $requester) {
    function xoops_isDeveloperRequest($dirname = null)
    {
        return false;
    }
} elseif ('developer' === $requester) {
    function xoops_isDeveloperRequest($dirname = null)
    {
        return true;
    }
} elseif ('throwing' === $requester) {
    function xoops_isDeveloperRequest($dirname = null)
    {
        throw new RuntimeException('the check itself failed');
    }
}

require XOOPS_ROOT_PATH . '/class/logger/xoopslogger.php';

define('_XOOPS_FATAL_MESSAGE', '<p>Error : %s</p>');
error_reporting(E_ALL);

$GLOBALS['recorded'] = [];
$logger = new XoopsLogger();
$logger->addLogger(new class {
    public function log($level, $message, array $context = []): void
    {
        $GLOBALS['recorded'][] = (string) $message;
    }
});

register_shutdown_function(static function (): void {
    echo "\n--RESULT--\n", json_encode([
        'status'   => http_response_code(),
        'recorded' => $GLOBALS['recorded'],
    ]);
});

if ('exception' === $entry) {
    $logger->handleException(new RuntimeException('MARKER-7f3a secret detail'));
} else {
    $logger->handleError(E_USER_ERROR, 'MARKER-7f3a secret detail', XOOPS_ROOT_PATH . '/somefile.php', 12);
}
