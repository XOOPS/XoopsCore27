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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Xoops\Upgrade\Smarty4RepairOutput;
use Xoops\Upgrade\Smarty4ScannerOutput;
use Xoops\Upgrade\Smarty4TemplateChecks;
use Xoops\Upgrade\Smarty4TemplateRepair;

/**
 * Quoting of xo* plugin arguments by the Smarty 4 preflight repair (issue #294).
 *
 * @category  Xoops\Upgrade\Tests
 * @package   Xoops
 * @author    XOOPS Development Team
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(Smarty4TemplateRepair::class)]
#[CoversClass(Smarty4TemplateChecks::class)]
final class Smarty4TemplateRepairTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/language/english/smarty4.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function tmpFile(string $contents): string
    {
        $dir = dirname(__DIR__) . '/tmp/work';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . '/' . uniqid('s4_', true) . '.tpl';
        file_put_contents($path, $contents);
        $this->tmpFiles[] = $path;

        return $path;
    }

    private function repair(string $contents): string
    {
        $path = $this->tmpFile($contents);
        (new Smarty4TemplateRepair(new Smarty4RepairOutput()))->inspectFile(new SplFileInfo($path));

        return (string) file_get_contents($path);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function repairCases(): array
    {
        return [
            'tag followed by tag'              => ['url(<{xoAppUrl /}><{$item.file}>)', "url(<{xoAppUrl '/'}><{\$item.file}>)"],
            'path followed by tag'             => ['<{xoAppUrl uploads/cadch/}><{$item.photo}>', "<{xoAppUrl 'uploads/cadch/'}><{\$item.photo}>"],
            'single-quoted attribute'          => ["<a href='<{xoAppUrl /}><{\$x}>'>", "<a href='<{xoAppUrl \"/\"}><{\$x}>'>"],
            'double-quoted attribute'          => ['<a href="<{xoAppUrl /}><{$x}>">', "<a href=\"<{xoAppUrl '/'}><{\$x}>\">"],
            'two plugin tags'                  => ['<{xoImgUrl a.png}><{xoImgUrl b.png}>', "<{xoImgUrl 'a.png'}><{xoImgUrl 'b.png'}>"],
            'variable stays a variable'        => ['<{xoAppUrl $var}>', '<{xoAppUrl $var}>'],
            'variable in double-quoted attr'   => ['<a href="<{xoAppUrl $item.file}>">', '<a href="<{xoAppUrl $item.file}>">'],
            'variable in single-quoted attr'   => ["<a href='<{xoAppUrl \$item.file}>'>", "<a href='<{xoAppUrl \$item.file}>'>"],
            'plain argument'                   => ['<{xoImgUrl style.css}>', "<{xoImgUrl 'style.css'}>"],
            'already quoted'                   => ["<{xoAppUrl 'a.php'}>", "<{xoAppUrl 'a.php'}>"],
            'earlier corruption is undone'     => ["url(<{xoAppUrl '/}><{\$item.file'}>)", "url(<{xoAppUrl '/'}><{\$item.file}>)"],
            'earlier corruption, double quote' => ["<{xoAppUrl \"/}><{\$x\"}>", '<{xoAppUrl "/"}><{$x}>'],
            'earlier corruption, three tags'   => ["<{xoAppUrl '/}><{\$dir}><{\$file'}>", "<{xoAppUrl '/'}><{\$dir}><{\$file}>"],
            'earlier corruption, trailing ws'  => ["<{xoAppUrl '/}><{\$file' }>", "<{xoAppUrl '/'}><{\$file }>"],
            'earlier corruption, variable'     => ["<{xoAppUrl '\$base}><{\$file'}>", '<{xoAppUrl $base}><{$file}>'],
            'old repair output, three tags'    => ['<{xoAppUrl /}><{$dir}><{$file}>', "<{xoAppUrl '/'}><{\$dir}><{\$file}>"],
            'earlier corruption, text between' => ["<a href='<{xoAppUrl \"/}> <{\$x\"}>'>", "<a href='<{xoAppUrl \"/\"}> <{\$x}>'>"],
            'earlier quoted lone variable'     => ["<{xoAppUrl '\$item.file'}>", '<{xoAppUrl $item.file}>'],
            'earlier double-quoted variable'   => ["<a href='<{xoAppUrl \"\$x\"}>'>", "<a href='<{xoAppUrl \$x}>'>"],
            'earlier quoted variable modifier' => ["<{xoAppUrl '\$x|escape'}>", '<{xoAppUrl $x|escape}>'],
            'earlier quoted indexed variable'  => ["<{xoAppUrl '\$a[0]'}>", '<{xoAppUrl $a[0]}>'],
            'earlier quoted second variable'   => ["<{xoAppUrl foo '\$x'}>", '<{xoAppUrl foo $x}>'],
            'earlier quoted var after literal' => ["<{xoAppUrl 'foo' '\$x'}>", "<{xoAppUrl 'foo' \$x}>"],
            'earlier quoted var, trailing ws'  => ["<a href=\"<{xoAppUrl '\$x '}>\">", '<a href="<{xoAppUrl $x}>">'],
            'old repair output, attr trailing' => ['<a href="<{xoAppUrl $x }>">', '<a href="<{xoAppUrl $x }>">'],
            'earlier corruption, $digit first' => ["<{xoAppUrl '\$5}><{\$x'}>", "<{xoAppUrl '\$5'}><{\$x}>"],
            'quoted $digit literal stays'      => ["<{xoAppUrl '\$5'}>", "<{xoAppUrl '\$5'}>"],
            'earlier quoted $digit variable'   => ["<{xoAppUrl '\$5foo'}>", '<{xoAppUrl $5foo}>'],
            'earlier corruption, $digit var'   => ["<{xoAppUrl '\$5foo}><{\$x'}>", '<{xoAppUrl $5foo}><{$x}>'],
            'earlier double-quoted dotted var' => ["<a href='<{xoAppUrl \"\$item.file\"}>'>", "<a href='<{xoAppUrl \$item.file}>'>"],
            // Only the old single-quote attribute pattern wrote double quotes;
            // elsewhere "$theme.css" is $theme followed by the text .css
            'double-quoted interpolation stays' => ['<{xoAppUrl "$theme.css"}>', '<{xoAppUrl "$theme.css"}>'],
            'quoted path with a $ stays'       => ["<{xoAppUrl 'a\$b.php'}>", "<{xoAppUrl 'a\$b.php'}>"],
            // The old repair quoted across an empty first argument too
            'earlier corruption, empty arg'    => ["<{xoAppUrl '}><{\$x'}>", '<{xoAppUrl }><{$x}>'],
            'earlier corruption, empty in attr' => ["<a href='<{xoAppUrl \"}><{\$x\"}>'>", "<a href='<{xoAppUrl }><{\$x}>'>"],
            'old repair output, empty arg'     => ['<{xoAppUrl }><{$x}>', '<{xoAppUrl }><{$x}>'],
            'empty tag in double-quoted attr'  => ['<a href="<{xoAppUrl }>">', '<a href="<{xoAppUrl }>">'],
            'empty tag in single-quoted attr'  => ["<a href='<{xoAppUrl }>'>", "<a href='<{xoAppUrl }>'>"],
        ];
    }

    #[Test]
    #[DataProvider('repairCases')]
    public function itQuotesOnlyTheArgumentOfItsOwnTag(string $input, string $expected): void
    {
        $once = $this->repair($input);
        self::assertSame($expected, $once);
        self::assertSame($expected, $this->repair($once), 'a second run must not change the result');
    }

    #[Test]
    public function theScannerFlagsQuotesSpanningTwoTags(): void
    {
        $path = $this->tmpFile("<{xoAppUrl '/}><{\$item.file'}>\n<{xoAppUrl 'ok.php'}><{\$x}>\n"
            . "<{xoAppUrl '/}><{\$dir}><{\$file'}>\n<{xoAppUrl '/}><{\$file' }>\n"
            . "<{xoAppUrl \"/}> <{\$x\"}>\n<{xoAppUrl '\$item.file'}>\n<{xoAppUrl 'a\$b.php'}>\n"
            . "<{xoAppUrl '\$x|escape'}>\n<{xoAppUrl foo '\$x'}>\n<{xoAppUrl 'foo' '\$x'}>\n<{xoAppUrl '\$5'}>\n<{xoAppUrl '\$x '}>\n"
            . "<{xoAppUrl '\$5foo'}>\n<a href='<{xoAppUrl \"\$item.file\"}>'>\n<{xoAppUrl \"\$theme.css\"}>");
        $output = new Smarty4ScannerOutput();
        (new Smarty4TemplateChecks($output))->inspectFile(new SplFileInfo($path));
        $output->outputWrapUp();
        $report = $output->outputFetch();

        self::assertStringContainsString('<td>brokenquotes</td><td>&lt;{xoAppUrl &#039;/}&gt;&lt;{$item.file&#039;}&gt;</td>', $report);
        self::assertStringContainsString('&lt;{xoAppUrl &#039;$5foo&#039;}&gt;', $report);
        self::assertStringNotContainsString('$theme.css', $report);
        self::assertSame(11, substr_count($report, '<td>brokenquotes</td>'));
        self::assertStringContainsString('earlier repair (auto-fixable)</td><td>11</td>', $report);
    }

    #[Test]
    public function theScannerReportKeepsAMatchWithInvalidUtf8(): void
    {
        $path = $this->tmpFile("<{xoAppUrl '/a\xFF}><{\$x'}>");
        $output = new Smarty4ScannerOutput();
        (new Smarty4TemplateChecks($output))->inspectFile(new SplFileInfo($path));

        self::assertStringContainsString("<td>&lt;{xoAppUrl &#039;/a\u{FFFD}}&gt;&lt;{\$x&#039;}&gt;</td>", $output->outputFetch());
    }
}
