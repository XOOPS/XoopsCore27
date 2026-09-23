<?php
/**
 * XOOPS Markdown editor selection regressions.
 * @copyright (c) 2000-2026 XOOPS Project
 * @license GNU GPL 2 or later
 */
/** Regression: a remembered HTML editor must never receive stored Markdown. */
require __DIR__ . '/markdown.php';
$_POST = [];
$GLOBALS['xoopsConfig']['language'] = 'english';

// Isolate discovery from the site's editor cache; exercise the real routing
// and the real EasyMDE adapter. HTML construction is the destructive boundary.
$handler = new class extends XoopsEditorHandler {
    public array $loaded = [];
    public function getList($noHtml = false) {
        $list = ['easymde' => 'Markdown', 'tinymce5' => 'TinyMCE5', 'tinymce7' => 'TinyMCE7'];
        return $this->allowed_editors ? array_intersect_key($list, array_flip($this->allowed_editors)) : $list;
    }
    public function _loadEditor($name, $options = null) {
        $this->loaded[] = $name;
        return $name === 'easymde' ? parent::_loadEditor($name, $options)
            : new XoopsFormTextArea('', $options['name'], $options['value']);
    }
};
$markdown = "# Heading\n\n| A | B |\n| --- | --- |\n| one | two |\n\n```php\n    echo '&lt;tag&gt;';\n```";
$storedMarkdown = XoopsMarkdown::wrap($markdown);
foreach (['tinymce5', 'tinymce7'] as $remembered) {
    foreach ([$storedMarkdown, htmlspecialchars($storedMarkdown, ENT_QUOTES | ENT_HTML5, 'UTF-8')] as $editValue) {
        $handler->loaded = [];
        $selected = $handler->get($remembered, ['name' => 'message', 'value' => $editValue]);
        check($selected instanceof FormEasyMDE, 'Stored Markdown must override remembered ' . $remembered . ' before HTML initialization');
        check(!in_array($remembered, $handler->loaded, true), 'HTML editor never receives Markdown storage source');
        $document = new DOMDocument();
        $document->loadHTML($selected->render());
        check($document->getElementsByTagName('textarea')->item(0)->textContent === $markdown, 'Source newlines, table and code survive editor selection');
    }
}
$handler->loaded = [];
$handler->get('tinymce7', ['name' => 'message', 'value' => '<p>HTML stays HTML</p>']);
check($handler->loaded === ['tinymce7'], 'Unmarked HTML keeps its selected editor');
$handler->allowed_editors = ['tinymce7'];
$handler->loaded = [];
$fallback = $handler->get('tinymce7', ['name' => 'message', 'value' => $storedMarkdown]);
check($handler->loaded === [] && $fallback instanceof XoopsFormTextArea, 'Unavailable Markdown editor falls back to a safe textarea, never HTML');
check($fallback->getValue() === $storedMarkdown, 'Fallback preserves stored Markdown exactly');

// The screenshot's damaged wrapper cannot be reconstructed, but reopening it
// must not add another entity-escaping layer on every Preview.
$damaged = '<p>[xoops:markdown="1"] # Heading &mdash; text [/xoops:markdown]</p>';
$editor = new FormEasyMDE(['name' => 'message', 'value' => htmlspecialchars($damaged, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
$document->loadHTML($editor->render());
check($document->getElementsByTagName('textarea')->item(0)->textContent === $damaged, 'Malformed marker does not cause repeated edit escaping');

// Use the actual wrapper, selector and renderer; only cache discovery is isolated.
class XoopsCache {
    public static function read($key) {
        return ['easymde' => ['title' => 'Markdown (EasyMDE)', 'nohtml' => 1], 'tinymce7' => ['title' => 'TinyMCE7', 'nohtml' => 0]];
    }
}
define('_SELECT', 'Select');
define('NWLINE', "\n");
require XOOPS_ROOT_PATH . '/class/xoopsform/form.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/formelementtray.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/formselect.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/formselecteditor.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/formeditor.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererInterface.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRenderer.php';
require XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRendererLegacy.php';
$form = new class('', 'editpost', '', 'post', false) extends XoopsForm {};
$selector = new XoopsFormSelectEditor($form, 'editor', 'tinymce7');
$form->addElement($selector);
$wrapper = new XoopsFormEditor('Body', 'tinymce7', ['name' => 'message', 'value' => htmlspecialchars($storedMarkdown, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
$form->addElement($wrapper);
check($wrapper->editor instanceof FormEasyMDE, 'Real shared form wrapper respects stored Markdown format');
$document->loadHTML($selector->render());
$choices = $document->getElementsByTagName('option');
check($choices->length === 1 && $choices->item(0)->getAttribute('value') === 'easymde' && $choices->item(0)->hasAttribute('selected'), 'Selector reflects the safe editor and does not offer a destructive HTML switch');
$wrapper->editor->setValue('<p>Ordinary HTML</p>');
$ordinarySelector = new XoopsFormSelectEditor($form, 'editor', 'tinymce7');
$document->loadHTML($ordinarySelector->render());
check($document->getElementsByTagName('option')->length === 2, 'Unmarked content retains normal editor choices');
// Reopen -> edit -> Preview -> Save using the actual format-aware selection.
$handler->allowed_editors = [];
$selected = $handler->get('tinymce7', ['name' => 'message', 'value' => htmlspecialchars($storedMarkdown, ENT_QUOTES | ENT_HTML5, 'UTF-8')]);
$document->loadHTML($selected->render());
$pairs = [];
foreach ($document->getElementsByTagName('input') as $input) {
    $pairs[] = urlencode($input->getAttribute('name')) . '=' . urlencode($input->getAttribute('value'));
}
parse_str(implode('&', $pairs), $post);
$post['message'] = $document->getElementsByTagName('textarea')->item(0)->textContent . "\n\nMore **tests**";
$request = $post;
XoopsMarkdown::preparePost($post, $request);
check(str_contains($myts->previewTarea($post['message']), '<strong>tests</strong>'), 'Reopened Markdown previews as Markdown after editing');
$post['_xoops_markdown_save'] = '1';
XoopsMarkdown::preparePost($post, $request);
check(XoopsMarkdown::source($post['message']) === $markdown . "\n\nMore **tests**", 'Reopened Markdown saves exact source without TinyMCE flattening');
echo "PASS: content-aware Markdown editor selection and safe fallback\n";
