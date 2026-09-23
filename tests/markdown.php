<?php
declare(strict_types=1);

/**
 * Standalone core Markdown regression check: php tests/markdown.php
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license GNU GPL 2 or later
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('XOOPS_ROOT_PATH', dirname(__DIR__) . '/htdocs');
define('XOOPS_VAR_PATH', XOOPS_ROOT_PATH . '/xoops_data');
define('XOOPS_TRUST_PATH', XOOPS_ROOT_PATH . '/xoops_lib');
define('XOOPS_URL', 'https://example.test');
define('XOOPS_UPLOAD_URL', XOOPS_URL . '/uploads');
define('_QUOTEC', 'Quote:');
require XOOPS_TRUST_PATH . '/vendor/autoload.php';
require XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$myts = (new ReflectionClass(MyTextSanitizer::class))->newInstanceWithoutConstructor();
$myts->config = ['extensions' => []];
$myts->smileys = [['code' => ':)', 'smile_url' => 'smile.png']];
$source = "# Heading\n\n**bold**\n\n| A | B |\n| --- | --- |\n| one | two |\n\n```php\n[b]literal[/b] :)\n    echo '<tag>';\n```";
$stored = '[xoops:markdown="1"]' . "\n" . strtr($source, ['<' => '%3C', '>' => '%3E']) . "\n[/xoops:markdown]";
$html = $myts->displayTarea($stored, 0, 1, 1, 1, 1);
check(str_contains($html, '<h1>Heading</h1>'), 'Saved Markdown must render through displayTarea');
check(str_contains($html, '<strong>bold</strong>') && str_contains($html, '<table>'), 'Bold and tables');
check(str_contains($html, "[b]literal[/b] :)\n    echo '&lt;tag&gt;';"), 'Code whitespace, BBCode, and smileys stay literal');
check($html === $myts->previewTarea($stored, 0, 1, 1, 1, 1), 'Preview uses the same renderer');

require_once XOOPS_ROOT_PATH . '/class/xoopsmarkdown.php';
check(XoopsMarkdown::wrap($source) === $stored, 'Stable storage marker');
check(XoopsMarkdown::wrap($stored) === $stored, 'No duplicate wrapper on resubmit');
check(XoopsMarkdown::wrap('  ') === '  ', 'Empty content must remain empty for validation');
check(XoopsMarkdown::source($stored) === $source, 'Raw source round trip');
$entities = "`&lt;tag&gt;` & \"quoted\" <tag>";
$encoded = htmlspecialchars(XoopsMarkdown::wrap($entities), ENT_QUOTES | ENT_HTML5, 'UTF-8');
check(XoopsMarkdown::source($encoded) === $entities, 'getVar(edit) escaping is decoded exactly once');
check(XoopsMarkdown::source(XoopsMarkdown::wrap($entities)) === $entities, 'Raw entity text is not decoded');
$reserved = "100% %3C %26 %25 %5B\n[/xoops:markdown]\n[xoops:markdown=\"1\"]";
check(XoopsMarkdown::source(XoopsMarkdown::wrap($reserved)) === $reserved, 'Percent sequences and reserved marker examples survive');
check(XoopsMarkdown::source(str_replace("\n", "\r\n", XoopsMarkdown::wrap('# Heading'))) === '# Heading', 'Browser CRLF normalization');

$attack = XoopsMarkdown::wrap('<script>alert(1)</script> <img src=x onerror=alert(1)> [bad](javascript:alert%281%29)');
foreach ([0, 1] as $htmlFlag) {
    $safe = $myts->displayTarea($attack, $htmlFlag);
    check(!str_contains($safe, '<script') && !str_contains($safe, '<img') && !str_contains($safe, 'href="javascript:'), 'Markdown never inherits raw HTML permission');
}
$image = XoopsMarkdown::wrap('![alternative](https://example.test/image.png)');
check(str_contains($myts->displayTarea($image, 0, 0, 0, 1), '<img'), 'Allowed Markdown image');
$noImage = $myts->displayTarea($image, 0, 0, 0, 0);
check(!str_contains($noImage, '<img') && str_contains($noImage, 'alternative'), 'Image restriction preserves alternative text');
check($myts->displayTarea('**legacy**', 0, 0, 0, 0, 0) === '**legacy**', 'Unmarked text is never guessed');
check(str_contains($myts->displayTarea('[b]legacy[/b]', 0, 0, 1), '<strong>legacy</strong>'), 'Legacy BBCode');
check(str_contains($myts->displayTarea('<strong>legacy</strong>', 1, 0, 0), '<strong>legacy</strong>'), 'Legacy HTML');
$quoted = "[quote]\nAuthor wrote:\n" . htmlspecialchars($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "[/quote]\nReply";
$quotedHtml = $myts->displayTarea($quoted, 0, 0, 1);
check(str_contains($quotedHtml, '<blockquote>') && str_contains($quotedHtml, '<h1>Heading</h1>'), 'NewBB-style quoted Markdown');
$quoteSource = XoopsMarkdown::editorSource("[quote]\nAuthor wrote:\n" . htmlspecialchars($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '[/quote]');
$markdownReply = $myts->displayTarea(XoopsMarkdown::wrap($quoteSource));
check(str_contains($markdownReply, '<blockquote>') && str_contains($markdownReply, '<h1>Heading</h1>'), 'Quoting a Markdown post into EasyMDE');
check(!str_contains($quoteSource, '[xoops:markdown='), 'Storage markers are hidden in quoted editor content');
$twoDocuments = $stored . "\nReply\n" . $stored;
check(substr_count($myts->displayTarea($twoDocuments, 0, 0, 0), '<h1>Heading</h1>') === 2, 'Concatenated article fields render separately');
$twoRendered = $myts->displayTarea("[quote]\n" . $twoDocuments . '[/quote]', 0, 0, 1);
check(substr_count($twoRendered, '<h1>Heading</h1>') === 2, 'Multiple marked documents inside a legacy quote');
$protectedCode = '[code]' . $stored . '[/code]';
check(XoopsMarkdown::protect($protectedCode) === [], 'Legacy code examples are not interpreted');

$post = ['message' => $source, 'body' => ['intro' => '# Intro'], 'subject' => 'Unchanged', '_xoops_markdown' => ['message', 'body[intro]']];
$post['_xoops_markdown_save'] = '1';
foreach ($post['_xoops_markdown'] as $field) {
    $post['_xoops_markdown_state'][hash('sha256', $field)] = ['initial' => hash('sha256', ''), 'marked' => '0'];
}
$request = $post;
XoopsMarkdown::preparePost($post, $request);
check($post['message'] === $stored && $post['body']['intro'] === XoopsMarkdown::wrap('# Intro'), 'Flat and nested editor fields are tagged centrally');
check($post['subject'] === 'Unchanged' && $request['message'] === $stored, 'Legacy request readers and unrelated fields');
XoopsMarkdown::preparePost($post, $request);
check($post['message'] === $stored, 'Request preparation is idempotent');
$_POST = ['message' => XoopsMarkdown::wrap($entities)];
foreach (['getString', 'getText'] as $method) {
    check(XoopsMarkdown::source(\Xmf\Request::$method('message', '', 'POST')) === $entities, 'Real XMF ' . $method . ' must preserve code and entities');
}
$bad = ['message' => ['invalid'], '_xoops_markdown' => ['message', ['bad'], 'missing', 'body[]']];
$badRequest = $bad;
XoopsMarkdown::preparePost($bad, $badRequest);
check($bad['message'] === ['invalid'] && !isset($bad['missing']), 'Malformed metadata never coerces input');

// Load the real editor classes, without booting a site or connecting to its DB.
function xoops_load(string $name): bool
{
    return class_exists($name, false);
}
require XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/formtextarea.php';
require XOOPS_ROOT_PATH . '/class/xoopseditor/xoopseditor.php';
require XOOPS_ROOT_PATH . '/class/xoopseditor/easymde/easymde.php';
$editor = new FormEasyMDE(['name' => 'message', 'value' => $encoded]);
$form = $editor->render();
$dom = new DOMDocument();
$dom->loadHTML('<!doctype html><html><body>' . $form . '</body></html>');
check($dom->getElementsByTagName('textarea')->item(0)->textContent === $entities, 'Editor shows exact source, without storage markers or double escaping');
$fields = [];
foreach ($dom->getElementsByTagName('input') as $input) {
    if ($input->getAttribute('name') === '_xoops_markdown[]') {
        $fields[] = $input->getAttribute('value');
    }
}
check($fields === ['message'], 'Editor declares its submitted field format without JavaScript');

// Exercise the actual standard object path used by Publisher/NewBB and future modules.
check(is_file(XOOPS_VAR_PATH . '/configs/textsanitizer/config.php'), 'Installed sanitizer config required; tests must not create site config');
require XOOPS_ROOT_PATH . '/kernel/object.php';
$article = new XoopsObject();
$article->initVar('body', XOBJ_DTYPE_TXTAREA, $stored);
check($article->getVar('body') === $html, 'XoopsObject textarea display renders Markdown centrally');
check($article->getVar('body', 'p') === $html, 'XoopsObject textarea preview');
check(XoopsMarkdown::source($article->getVar('body', 'e')) === $source, 'XoopsObject edit round trip');
check($article->getVar('body', 'n') === $stored, 'Raw object value remains storage source');
if (($argv[1] ?? '') === '--javascript') {
    foreach ($dom->getElementsByTagName('script') as $script) {
        if (!$script->hasAttribute('src')) {
            echo $script->textContent, "\n";
        }
    }
    exit;
}
echo "PASS: central Markdown rendering, security, legacy formats, quotes, request transport, and editor round trip\n";
