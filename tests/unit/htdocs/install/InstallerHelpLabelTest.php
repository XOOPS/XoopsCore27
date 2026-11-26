<?php
/**
 * Installer help text for current and older language packs.
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Tests
 * @package   install
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

declare(strict_types=1);

namespace install;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InstallerHelpLabelTest extends TestCase
{
    public static function labels(): iterable
    {
        yield 'older language pack' => [null, 'Show/hide help text'];
        yield 'translated label' => ['Aide <détails> & options', 'Aide &lt;détails&gt; &amp; options'];
    }

    #[DataProvider('labels')]
    public function testRendersHelpButton(?string $label, string $expected): void
    {
        // A clean process lets each pack supply its own constants before the template renders.
        $code = <<<'PHP'
define('XOOPS_INSTALL', true);
define('XOOPS_ROOT_PATH', dirname(getcwd()));
define('_INSTALL_CHARSET', 'UTF-8');
foreach (['XOOPS_INSTALL_WIZARD', 'SUPPORT', 'XOOPS_SOURCE_CODE', 'XOOPS_INSTALLING', 'BUTTON_NEXT'] as $key) {
    define($key, $key);
}
if (isset($argv[1])) {
    define('SHOW_HIDE_HELP', $argv[1]);
}
require '../include/version.php';
require 'include/functions.php';
require 'class/installwizard.php';
$wizard = new XoopsInstallWizard();
$wizard->pages[$wizard->currentPage] = ['name' => 'Settings', 'title' => 'Settings', 'icon' => 'fa fa-cog'];
$pageHasForm = $pageHasHelp = true;
$content = '<div class="xoform-help collapse">Help</div>';
include 'include/install_tpl.php';
PHP;
        $command = [PHP_BINARY, '-r', $code, '--'];
        if ($label !== null) {
            $command[] = $label;
        }
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, XOOPS_ROOT_PATH . '/install');
        self::assertIsResource($process);
        $html = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        self::assertSame(0, proc_close($process), $html);
        self::assertMatchesRegularExpression('~<button\b[^>]*data-bs-target="\.xoform-help"[^>]*>' . preg_quote($expected, '~') . '</button>~', $html);
    }
}
