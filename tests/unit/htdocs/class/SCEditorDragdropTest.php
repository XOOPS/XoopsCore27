<?php

declare(strict_types=1);

/**
 * The SCEditor dragdrop plugin is handed an upload token only when an image
 * category is configured and the viewer is a logged-in user who may upload to it.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/xoopsform/formelement.php';
require_once XOOPS_ROOT_PATH . '/class/xoopsform/formtextarea.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/xoopseditor.php';
require_once XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/sceditor.php';
require_once XOOPS_ROOT_PATH . '/kernel/user.php';

#[CoversClass(FormSCEditor::class)]
final class SCEditorDragdropTest extends TestCase
{
    private mixed $savedUser = null;

    protected function setUp(): void
    {
        $this->savedUser = $GLOBALS['xoopsUser'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['xoopsUser'] = $this->savedUser;
    }

    private function config(int $imgcatId): ?array
    {
        $editor = (new ReflectionClass(FormSCEditor::class))->newInstanceWithoutConstructor();
        return (new ReflectionMethod($editor, 'dragdropConfig'))->invoke($editor, $imgcatId);
    }

    private function member(): XoopsUser
    {
        $user = new XoopsUser();
        $user->assignVar('uid', 5);
        return $user;
    }

    #[Test]
    public function offWhenNoCategoryIsConfigured(): void
    {
        $GLOBALS['xoopsUser'] = $this->member();
        $this->assertNull($this->config(0));
    }

    #[Test]
    public function offForGuestsEvenWithACategory(): void
    {
        $GLOBALS['xoopsUser'] = '';
        $this->assertNull($this->config(3));
    }

    #[Test]
    public function offWhenTheCategoryDoesNotExist(): void
    {
        // The test database stub finds no rows, so category 3 is unknown.
        $GLOBALS['xoopsUser'] = $this->member();
        $this->assertNull($this->config(3));
    }

    #[Test]
    public function negativeOrMissingPreferenceMeansOff(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/xoopseditor/sceditor/class/SCEditorConfig.php';
        $this->assertSame(0, SCEditorConfig::settings([])['dragdrop_cat']);
        $this->assertSame(0, SCEditorConfig::settings(['sceditor_dragdrop_cat' => '-4'])['dragdrop_cat']);
        $this->assertSame(12, SCEditorConfig::settings(['sceditor_dragdrop_cat' => '12'])['dragdrop_cat']);
        $this->assertSame('0', SCEditorConfig::items()['sceditor_dragdrop_cat']['value']);
    }
}
