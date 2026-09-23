<?php
/**
 * XOOPS list rendering regression.
 * @copyright (c) 2000-2026 XOOPS Project
 * @license GNU GPL 2 or later
 */
declare(strict_types=1);
require __DIR__ . '/markdown.php';
require XOOPS_ROOT_PATH . '/class/textsanitizer/ul/ul.php';
require XOOPS_ROOT_PATH . '/class/textsanitizer/li/li.php';
$myts->config['extensions'] = ['ul' => 1, 'li' => 1];
$myts->path_basic = XOOPS_ROOT_PATH . '/class/textsanitizer';
$myts->path_plugin = XOOPS_ROOT_PATH . '/class/textsanitizer';
$html = $myts->displayTarea("[ul]\n[li]one[/li]\n[li]two[/li]\n[/ul]", 0, 0, 1, 1, 1);
if (str_contains($html, '<br>') || !str_contains($html, '<ul><li>one</li><li>two</li></ul>')) {
    throw new RuntimeException('List rendering must not add line-break elements between list items: ' . $html);
}
echo "PASS: list rendering has no synthetic item spacing\n";
