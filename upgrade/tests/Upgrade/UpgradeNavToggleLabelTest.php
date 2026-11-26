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

namespace Xoops\Upgrade\Tests\Upgrade;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The upgrader's menu button is named for screen readers, with current and older language packs.
 *
 * @category  Tests
 * @package   Xoops\Upgrade\Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
final class UpgradeNavToggleLabelTest extends TestCase
{
    public static function toggleLabels(): iterable
    {
        yield 'older language pack' => [null, 'Toggle navigation'];
        yield 'translated label' => ['Menu "principal" & étapes', 'Menu &quot;principal&quot; &amp; étapes'];
    }

    #[DataProvider('toggleLabels')]
    public function testNamesTheNavigationToggle(?string $label, string $expected): void
    {
        $html = $this->render($label);

        self::assertMatchesRegularExpression('~<button class="navbar-toggler"[^>]*aria-label="' . preg_quote($expected, '~') . '"~', $html);
    }

    /**
     * Render the upgrader page in a clean process, so each case supplies its own language constant.
     *
     * @param string|null $label translation of _TOGGLE_NAVIGATION, or null for a language pack that lacks it
     */
    private function render(?string $label): string
    {
        $code = <<<'PHP'
define('XOOPS_ROOT_PATH', dirname(getcwd()));
define('XOOPS_URL', 'http://localhost');
define('XOOPS_VERSION', 'XOOPS 2.7.5');
foreach (['_LANGCODE', '_LANGUAGE', '_SUPPORT', '_XOOPS_UPGRADE', '_XOOPS_SOURCE_CODE', '_PATCH_COUNT'] as $key) {
    define($key, $key);
}
if (isset($argv[1])) {
    define('_TOGGLE_NAVIGATION', $argv[1]);
}
$viewModel = [
    'upgradeLanguage' => 'english',
    'languages'       => ['english'],
    'supportSites'    => [],
    'upgradeQueue'    => [],
    'preflightDone'   => true,
    'hasError'        => false,
    'patchCount'      => 0,
    'content'         => '',
];
include 'upgrade_tpl.php';
PHP;
        $command = [PHP_BINARY, '-r', $code, '--'];
        if ($label !== null) {
            $command[] = $label;
        }
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, dirname(__DIR__, 2));
        self::assertIsResource($process);
        $html = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        self::assertSame(0, proc_close($process), $html);

        return $html;
    }
}
