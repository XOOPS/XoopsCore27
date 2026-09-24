<?php
declare(strict_types=1);

/**
 * Read-only Markdown preview using the same safe renderer as saved content.
 * @copyright Copyright (c) XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 or later
 */
require dirname(__DIR__, 3) . '/mainfile.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsmarkdown.php';

$GLOBALS['xoopsLogger']->activated = false;
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo '{}';
    exit;
}
if (!\Xmf\Request::hasVar('markdown', 'POST')) {
    http_response_code(400);
    echo '{}';
    exit;
}
$source = \Xmf\Request::getText('markdown', '', 'POST');
// Bound work on this public, read-only preview endpoint to one MiB of source.
if (strlen($source) > 1048576) {
    http_response_code(413);
    echo '{}';
    exit;
}
echo json_encode(['html' => XoopsMarkdown::render($source)], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
