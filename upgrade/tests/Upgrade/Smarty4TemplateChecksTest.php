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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Xoops\Upgrade\Smarty4ScannerOutput;
use Xoops\Upgrade\Smarty4TemplateChecks;

/**
 * The Smarty 4 scanner reads every template of a site. An empty template must
 * not stop the scan, and the report names a template by its path inside the
 * site, whichever directory separator the pathname uses.
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(Smarty4TemplateChecks::class)]
final class Smarty4TemplateChecksTest extends TestCase
{
    private string $path = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/language/english/smarty4.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    private function template(string $contents): void
    {
        $dir = dirname(__DIR__) . '/tmp/work';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->path = $dir . '/' . uniqid('s4c_', true) . '.tpl';
        file_put_contents($this->path, $contents);
    }

    private function scan(string $contents, bool $forwardSlashes = false): string
    {
        $this->template($contents);

        $output = new Smarty4ScannerOutput();
        $pathname = $forwardSlashes ? str_replace('\\', '/', $this->path) : $this->path;
        (new Smarty4TemplateChecks($output))->inspectFile(new SplFileInfo($pathname));

        return $output->outputFetch();
    }

    #[Test]
    public function anEmptyTemplateIsSkipped(): void
    {
        self::assertStringNotContainsString('<td>', $this->scan(''));
    }

    #[Test]
    public function aTemplateThatCannotBeReadIsReportedByItsPathInsideTheSite(): void
    {
        if ('\\' === DIRECTORY_SEPARATOR || (function_exists('posix_geteuid') && 0 === posix_geteuid())) {
            self::markTestSkipped('needs a file that cannot be read');
        }
        $this->template('<{includeq file="a.tpl"}>');
        chmod($this->path, 0200);

        $warnings = [];
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });
        try {
            (new Smarty4TemplateChecks(new Smarty4ScannerOutput()))->inspectFile(new SplFileInfo($this->path));
        } finally {
            restore_error_handler();
        }

        self::assertSame(['Error reading file: /tmp/work/' . basename($this->path)], $warnings);
    }

    #[Test]
    public function theReportNamesATemplateByItsPathInsideTheSite(): void
    {
        // On Windows the site root holds "\" while a pathname may arrive with "/".
        $report = $this->scan('<{includeq file="a.tpl"}>', true);

        self::assertStringContainsString('<td>/tmp/work/' . basename($this->path) . '</td>', $report);
    }
}
