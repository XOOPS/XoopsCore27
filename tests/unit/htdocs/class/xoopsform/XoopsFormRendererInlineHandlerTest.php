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

namespace xoopsclass;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Field names reach inline event handlers (textarea position tracking, the
 * preview button, the calendar button). The browser decodes the attribute
 * before it compiles the handler, so an HTML-encoded name can still end the
 * JavaScript string: every renderer must emit each name as one JS string literal.
 *
 * @category  Xoops
 * @package   XoopsForm
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class XoopsFormRendererInlineHandlerTest extends TestCase
{
    private const HOSTILE_NAMES = [
        'single quote'  => "x');alert(1);//",
        'double quote'  => 'x");alert(1);//',
        'markup'        => 'x"><img src=x onerror=alert(1)>',
        'backslash'     => 'x\\\');alert(1);//',
    ];

    public static function setUpBeforeClass(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/xoopsformloader.php';
        foreach (['Legacy', 'Bootstrap3', 'Bootstrap4', 'Bootstrap5', 'Tailwind'] as $r) {
            require_once XOOPS_ROOT_PATH . '/class/xoopsform/renderer/XoopsFormRenderer' . $r . '.php';
        }
    }

    /** @return array<string, array{string, string}> */
    public static function rendererAndName(): array
    {
        $cases = [];
        foreach (['Legacy', 'Bootstrap3', 'Bootstrap4', 'Bootstrap5', 'Tailwind'] as $renderer) {
            foreach (self::HOSTILE_NAMES as $label => $name) {
                $cases[$renderer . ' / ' . $label] = [$renderer, $name];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('rendererAndName')]
    public function aFieldNameStaysOneStringInEveryInlineHandler(string $renderer, string $name): void
    {
        $class = 'XoopsFormRenderer' . $renderer;
        $r     = new $class();

        $html = $this->render($r, 'renderFormDhtmlTextArea', new \XoopsFormDhtmlTextArea('caption', $name, 'text', 5, 50))
            . $this->render($r, 'renderFormTextDateSelect', new \XoopsFormTextDateSelect('caption', $name, 15, time()));

        $handlers = $this->handlers($html);
        $calls    = array_filter($handlers, static fn (string $js): bool => (bool) preg_match('/(xoopsSavePosition|form_instantPreview|showCalendar)\(/', $js));
        self::assertNotEmpty($calls, 'the renderer emits the handlers under test');

        foreach ($calls as $js) {
            // One call, with only string-literal or numeric arguments, nothing after it.
            self::assertMatchesRegularExpression(
                '/^\s*(?:return\s+)?(?:xoopsSavePosition|form_instantPreview|showCalendar)\(\s*'
                . '(?:"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|\d+)'
                . '(?:\s*,\s*(?:"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|\d+))*\s*\)\s*;?\s*(?:return false;?)?\s*$/',
                $js,
                $renderer . ': ' . $js
            );
            self::assertStringNotContainsString('alert(1)', preg_replace('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/', '', $js), 'code outside the string literals');
        }

        // The name comes back intact from the JSON literal of a position handler.
        foreach ($calls as $js) {
            if (preg_match('/^xoopsSavePosition\(("(?:[^"\\\\]|\\\\.)*")\);$/', trim($js), $m)) {
                self::assertSame($name, json_decode($m[1]), $renderer);
            }
        }
    }

    #[Test]
    public function aButtonTrayTypeIsOneOfTheThreeButtonTypes(): void
    {
        self::assertSame('submit', (new \XoopsFormButtonTray('n', 'v', 'x" onclick="alert(1)'))->getType());
        self::assertSame('submit', (new \XoopsFormButtonTray('n', 'v'))->getType());
        self::assertSame('reset', (new \XoopsFormButtonTray('n', 'v', 'RESET'))->getType());
        self::assertSame('button', (new \XoopsFormButtonTray('n', 'v', 'button'))->getType());
    }

    private function render(object $renderer, string $method, object $element): string
    {
        ob_start();
        $returned = $renderer->{$method}($element);

        return (string) ob_get_clean() . (string) $returned;
    }

    /** @return string[] decoded on* attribute values */
    private function handlers(string $html): array
    {
        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<!doctype html><meta charset="utf-8"><body>' . $html . '</body>', LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $out = [];
        foreach ((new DOMXPath($doc))->query('//@*[starts-with(name(), "on")]') as $attr) {
            $out[] = $attr->value;
        }

        return $out;
    }
}
