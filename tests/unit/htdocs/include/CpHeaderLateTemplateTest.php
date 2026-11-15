<?php
/**
 * Tests for the late template_main switch in include/cp_functions.php
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   core
 * @since     2.7.5
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Since xoops_cp_header() renders once per request, the confirmation and error
 * branches of the system admin pages (module install/update/uninstall, block
 * delete, avatar and rank save errors, image category edit) set a new
 * template_main and call the header again to no effect. The footer then raised
 * "template_main should be defined before call xoops_cp_header" on every hit.
 * The repeated call now switches the theme's content template.
 *
 * Runs in a separate process: cp_functions.php defines XOOPS_CPFUNC_LOADED and
 * Manage2faControllerTest declares its own xoops_cp_header() stub.
 */
final class CpHeaderLateTemplateTest extends TestCase
{
    private function load(): object
    {
        require_once XOOPS_ROOT_PATH . '/include/cp_functions.php';
        $theme                  = new stdClass();
        $theme->contentTemplate = 'db:system_modules.tpl';
        $GLOBALS['xoTheme']     = $theme;
        $GLOBALS['xoopsOption'] = ['template_main' => 'db:system_modules.tpl'];

        return $theme;
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aLateTemplateBecomesTheContentTemplate(): void
    {
        $theme = $this->load();
        $GLOBALS['xoopsOption']['template_main'] = 'system_modules_confirm.tpl';

        xoops_cp_switch_template();

        $this->assertSame('db:system_modules_confirm.tpl', $theme->contentTemplate);
        // The footer fallback compares these two and warns when they differ.
        $this->assertSame($theme->contentTemplate, $GLOBALS['xoopsOption']['template_main']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aTemplateWithAResourceIsKept(): void
    {
        $theme = $this->load();
        $GLOBALS['xoopsOption']['template_main'] = 'file:/x/confirm.tpl';

        xoops_cp_switch_template();

        $this->assertSame('file:/x/confirm.tpl', $theme->contentTemplate);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function noTemplateLeavesTheThemeAlone(): void
    {
        $theme = $this->load();
        unset($GLOBALS['xoopsOption']['template_main']);

        xoops_cp_switch_template();

        $this->assertSame('db:system_modules.tpl', $theme->contentTemplate);
        $this->assertArrayNotHasKey('template_main', $GLOBALS['xoopsOption']);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function noThemeYetIsANoOp(): void
    {
        $this->load();
        unset($GLOBALS['xoTheme']);
        $GLOBALS['xoopsOption']['template_main'] = 'system_header.tpl';

        xoops_cp_switch_template();

        $this->assertSame('system_header.tpl', $GLOBALS['xoopsOption']['template_main']);
    }

    #[Test]
    public function aRepeatedHeaderCallSwitchesTheTemplate(): void
    {
        // The static once-guard cannot be primed without a full control panel,
        // so the routing is pinned on the source text.
        $source = (string) file_get_contents(XOOPS_ROOT_PATH . '/include/cp_functions.php');
        $this->assertMatchesRegularExpression(
            '/if \(\$rendered\) \{\s*xoops_cp_switch_template\(\);\s*return;/',
            $source,
        );
    }
}
