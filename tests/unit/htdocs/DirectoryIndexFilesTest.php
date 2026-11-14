<?php
/**
 * Source-level guard: every shipped web directory carries an index file.
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category        Tests
 * @package         core
 * @author          XOOPS Development Team
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license         GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link            https://xoops.org
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * XOOPS ships no root .htaccess, so on an Apache host with directory listing
 * enabled, any shipped directory without an index file answers with an
 * "Index of" page. A security scanner reports that as Directory Browsing.
 *
 * The XOOPS convention is an index.php that returns 404. This test fails when
 * htdocs or a tracked directory under it has no index.php / index.html,
 * so a new editor, theme or library drop cannot bring listings back.
 */
#[CoversNothing]
final class DirectoryIndexFilesTest extends TestCase
{
    /** Trees denied as a whole by their own .htaccess. */
    private const EXCLUDED = '#^htdocs/(xoops_lib|xoops_data)(/|$)#';

    /** Must be in the scan; its absence means git is not seeing htdocs. */
    private const SENTINEL_DIR = 'htdocs/class/xoopseditor/sceditor/emoticons';

    #[Test]
    public function everyShippedDirectoryHasAnIndexFile(): void
    {
        $files = self::trackedFiles(dirname(__DIR__, 3));

        $directories = [];
        $indexed     = [];
        foreach ($files as $file) {
            $directory = dirname($file);
            // index.htm is not in Apache's default DirectoryIndex, so it does not count.
            if (1 === preg_match('#/index\.(php|html)$#', $file)) {
                $indexed[$directory] = true;
            }
            // Parents holding only subdirectories are listed by Apache too.
            for (; '.' !== $directory; $directory = dirname($directory)) {
                $directories[$directory] = true;
            }
        }

        $this->assertArrayHasKey(self::SENTINEL_DIR, $directories, 'Scan did not see htdocs');

        $missing = [];
        foreach (array_keys($directories) as $directory) {
            if (!isset($indexed[$directory]) && 1 !== preg_match(self::EXCLUDED, $directory)) {
                $missing[] = $directory;
            }
        }
        sort($missing);

        $this->assertSame(
            [],
            $missing,
            "Add the standard index.php (copy htdocs/themes/xswatch5/css-cerulean/index.php) to:\n" . implode("\n", $missing)
        );
    }

    /**
     * @param  string $root repository root
     * @return list<string> repo-relative paths of tracked files under htdocs
     */
    private static function trackedFiles(string $root): array
    {
        // Git's index, not a filesystem walk: local scratch directories under
        // htdocs are untracked and do not ship.
        if (!function_exists('exec')) {
            self::markTestSkipped('exec() is disabled');
        }

        $output = [];
        $status = 1;
        exec(sprintf('git -C %s ls-files -z -- htdocs', escapeshellarg($root)), $output, $status);
        if (0 !== $status || [] === $output) {
            self::markTestSkipped('No git checkout to scan');
        }

        return array_values(array_filter(explode("\0", implode("\n", $output))));
    }
}
