<?php
/**
 * XOOPS editor round-trip regressions.
 * @copyright (c) 2000-2026 XOOPS Project
 * @license GNU GPL 2 or later
 */
/** Database-free regressions for editor switching and NewBB's POST readers. */
use Xmf\Request;

require __DIR__ . '/markdown.php';

$original = '<p><strong>Reading the Overview.</strong> &amp; details</p><table><tr><td>Manifest</td><td>module.json</td></tr></table>';
if (($argv[1] ?? '') === '--html') {
    // Execute the actual message-reader expressions, without booting NewBB or saving posts.
    $code = file_get_contents(XOOPS_ROOT_PATH . '/modules/newbb/post.php');
    preg_match_all('/^\s*\$(p_message|message|hidden)\s*=\s*(?:htmlspecialchars\()?Request::get(?:String|Text)\([^\n]+;/m', $code, $reads, PREG_SET_ORDER);
    check(count($reads) >= 4, 'Find save, preview, redisplay and quote input readers');
    $_POST = ['message' => $original, 'hidden' => $original];
    foreach ($reads as $read) {
        eval('use Xmf\\Request; ' . $read[0]);
        $value = ${$read[1]};
        check($value === $original || htmlspecialchars_decode($value, ENT_QUOTES | ENT_HTML5) === $original, 'NewBB ' . $read[1] . ' must preserve HTML and entities');
    }
    require XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererInterface.php';
    require XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererLegacy.php';
    require XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererBootstrap5.php';
    // Both TinyMCE adapters delegate their textarea to these core renderers.
    foreach ([new XoopsFormRendererLegacy(), new XoopsFormRendererBootstrap5()] as $renderer) {
        $element = new XoopsFormTextArea('', 'message', $message);
        $document = new DOMDocument();
        $document->loadHTML($renderer->renderFormTextArea($element));
        check($document->getElementsByTagName('textarea')->item(0)->textContent === $original, 'TinyMCE textarea redisplay preserves exact HTML and entities');
    }
    preg_match('/\$dohtml\s*= Request::getInt\([^;]+;/s', substr($code, strpos($code, '$p_subject =')), $gate);
    foreach ([false, true] as $allowed) {
        $topicHandler = new class($allowed) {
            public function __construct(private bool $allowed) {}
            public function getPermission($forum, $status, $permission): bool {
                check($permission === 'html', 'Preview checks the HTML permission');
                return $this->allowed;
            }
        };
        $forumObject = null;
        $topic_status = 0;
        $_POST['dohtml'] = '1';
        eval('use Xmf\\Request; ' . $gate[0]);
        check((bool) $dohtml === $allowed, 'HTML preview cannot bypass forum permissions');
    }
    check(str_contains($myts->previewTarea($original, 1, 0, 0, 1, 0), '<table>'), 'HTML preview preserves table structure');
    check(!str_contains($myts->previewTarea($original, 0, 0, 0, 1, 0), '<table>'), 'HTML-disabled preview remains escaped');
    echo "PASS: NewBB HTML input round trip\n";
    exit;
}

$key = hash('sha256', 'message');
$metadata = ['field' => 'message', 'initial' => hash('sha256', $original), 'marked' => '0'];
$post = ['message' => $original, '_xoops_markdown' => ['message'], '_xoops_markdown_state' => [$key => $metadata]];
$request = $post;
XoopsMarkdown::preparePost($post, $request);
check($post['message'] === $original, 'Opening and switching an unchanged HTML post must not add a Markdown marker');
$post['_xoops_markdown_save'] = '1';
XoopsMarkdown::preparePost($post, $request);
check($post['message'] === $original, 'Saving unchanged HTML from EasyMDE must not tag it');
$post['message'] = '# Changed';
$post['_xoops_markdown_save'] = '0';
$request = $post;
XoopsMarkdown::preparePost($post, $request);
check($post['message'] === '# Changed', 'Preview/editor switch must not introduce a new marker even after edits');
check($myts->previewTarea('# Changed') === '<h1>Changed</h1>', 'Unsaved Markdown preview renders without mutating POST');
check(str_contains($myts->previewTarea('[size=&quot;x-large&quot;]Title[/size]'), 'font-size: x-large'), 'Preview decodes editor-encoded BBCode attributes');
// Redisplaying after Preview must retain the original baseline, not accept the
// changed body as the baseline (which would suppress the subsequent Save).
$_POST = $post;
$redisplay = new FormEasyMDE(['name' => 'message', 'value' => '# Changed']);
$document = new DOMDocument();
$document->loadHTML($redisplay->render());
$pairs = [];
foreach ($document->getElementsByTagName('input') as $input) {
    $pairs[] = urlencode($input->getAttribute('name')) . '=' . urlencode($input->getAttribute('value'));
}
parse_str(implode('&', $pairs), $post);
$post['message'] = $document->getElementsByTagName('textarea')->item(0)->textContent;
check($post['_xoops_markdown_state'][$key]['initial'] === hash('sha256', $original), 'Preview preserves original edit baseline');
$post['_xoops_markdown_save'] = '1';
$request = $post;
XoopsMarkdown::preparePost($post, $request);
check($post['message'] === "[xoops:markdown=\"1\"]\n# Changed\n[/xoops:markdown]", 'Changed Markdown receives marker on Save');
$post['message'] = '# Existing';
$post['_xoops_markdown_state'][$key]['marked'] = '1';
$post['_xoops_markdown_state'][$key]['initial'] = hash('sha256', '# Existing');
XoopsMarkdown::preparePost($post, $request);
check(XoopsMarkdown::source($post['message']) === '# Existing', 'Existing Markdown retains its format on an unchanged save');
$_POST = [];
$htmlEditor = new FormEasyMDE(['name' => 'message', 'value' => htmlspecialchars($original, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
$document->loadHTML($htmlEditor->render());
check($document->getElementsByTagName('textarea')->item(0)->textContent === $original, 'Opening HTML in EasyMDE must not double-escape it');
if (class_exists('Dom\\HTMLDocument')) {
    // HTML5 (unlike legacy DOMDocument) consumes the first textarea newline.
    foreach (["\n", "\r\n", "\r"] as $newline) {
        $leading = $newline . $original;
        $editor = new FormEasyMDE(['name' => 'message', 'value' => htmlspecialchars($leading, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
        $html5 = Dom\HTMLDocument::createFromString($editor->render(), LIBXML_NOERROR);
        $submitted = $html5->getElementsByTagName('textarea')->item(0)->textContent;
        check($submitted === "\n" . $original, 'HTML5 textarea retains leading newline, without falsely detecting an edit');
    }
}
$post = ['message' => "# Same\r\n\r\ntext", '_xoops_markdown' => ['message'], '_xoops_markdown_save' => '1',
    '_xoops_markdown_state' => [$key => ['initial' => hash('sha256', "# Same\n\ntext"), 'marked' => '0']]];
$request = $post;
XoopsMarkdown::preparePost($post, $request);
check(!str_contains($post['message'], '[xoops:markdown='), 'Browser newline normalization is not a user edit');
echo "PASS: save-only Markdown conversion\n";
