<?php
/**
 * Tests for the cached advisory request behind the Composer Packages panel
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
 * @package   system
 * @since     2.7.5
 */

declare(strict_types=1);

namespace modulessystem;

use ComposerAdvisories;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use XoopsCache;

require_once XOOPS_ROOT_PATH . '/class/cache/xoopscache.php';
require_once XOOPS_ROOT_PATH . '/modules/system/themes/ComposerAdvisories.php';

/**
 * The request is a stand-in that counts its calls: no test here uses the network.
 *
 * Runs in its own process: the cache is one shared object, and each test points
 * it at a folder of its own.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ComposerAdvisoriesCacheTest extends TestCase
{
    private const PACKAGES = [
        ['name' => 'acme/in-range', 'version' => '1.2.3'],
        ['name' => 'acme/clean', 'version' => '1.0.0'],
    ];

    private string $cachePath = '';

    /** @var list<string> every URL the stand-in was asked for */
    private array $requests = [];

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/xoadvisories_' . bin2hex(random_bytes(6));
        mkdir($this->cachePath, 0777, true);
        $settings = ['engine' => 'file', 'path' => $this->cachePath];
        $cache = XoopsCache::getInstance();
        $cache->config('default', $settings);
        $cache->engine('file', $settings);
        if (!XoopsCache::write('advisories_probe', 1, 60)) {
            $this->markTestSkipped('The file cache engine cannot write to a temporary folder here.');
        }
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->cachePath . '/*') as $file) {
            unlink((string) $file);
        }
        rmdir($this->cachePath);
    }

    /**
     * @param string|false $body what the request returns
     *
     * @return callable
     */
    private function answering($body): callable
    {
        return function (string $url) use ($body) {
            $this->requests[] = $url;

            return $body;
        };
    }

    private static function fixture(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4) . '/fixtures/packagist-advisories.json');
    }

    #[Test]
    public function theRequestCarriesPackageNamesAndNothingElse(): void
    {
        ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(self::fixture()));

        $this->assertSame(
            ['https://packagist.org/api/security-advisories/?packages%5B%5D=acme%2Fclean&packages%5B%5D=acme%2Fin-range'],
            $this->requests
        );
    }

    #[Test]
    public function aGoodAnswerMarksTheAffectedPackageAndIsNotAskedForAgain(): void
    {
        $first  = ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(self::fixture()));
        $second = ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(false));

        $this->assertTrue($first['available']);
        $this->assertSame(['acme/in-range'], array_keys($first['advisories']));
        $this->assertSame($first, $second);
        $this->assertCount(1, $this->requests);
    }

    #[Test]
    public function aFailedRequestIsReportedAsUnavailableAndNotRepeatedAtOnce(): void
    {
        $first  = ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(false));
        $second = ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(self::fixture()));

        $this->assertSame(['available' => false, 'advisories' => []], $first);
        $this->assertSame($first, $second);
        $this->assertCount(1, $this->requests);
    }

    #[Test]
    public function anAnswerThatIsNotTheExpectedJsonIsUnavailable(): void
    {
        $bodies = [
            '<html>blocked by a proxy</html>',
            '{"advisories": "none"}',
            '[]',
            '',
            // a package entry that is no list, and an advisory without a range: "nothing found" must not rest on these
            '{"advisories": {"acme/in-range": "broken", "acme/clean": []}}',
            '{"advisories": {"acme/in-range": [{"title": "no range given"}], "acme/clean": []}}',
            '{"advisories": {"acme/in-range": ["text"], "acme/clean": []}}',
        ];
        foreach ($bodies as $body) {
            XoopsCache::delete(ComposerAdvisories::CACHE_KEY_FAILED);

            $this->assertSame(
                ['available' => false, 'advisories' => []],
                ComposerAdvisories::forPackages(self::PACKAGES, $this->answering($body))
            );
        }
    }

    #[Test]
    public function aFailedAttemptStoredAfterAGoodAnswerDoesNotHideIt(): void
    {
        // Two requests miss the cache together. The other one stores a good answer while
        // this one is still under way; this one then fails and stores its failed attempt last.
        $failing = function (string $url) {
            ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(self::fixture()));

            return false;
        };

        $failed = ComposerAdvisories::forPackages(self::PACKAGES, $failing);
        $later  = ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(false));

        $this->assertFalse($failed['available']);
        $this->assertIsArray(XoopsCache::read(ComposerAdvisories::CACHE_KEY_FAILED), 'the failed attempt was stored last');
        $this->assertTrue($later['available']);
        $this->assertSame(['acme/in-range'], array_keys($later['advisories']));
        $this->assertCount(1, $this->requests);
    }

    #[Test]
    public function aWarningFromTheCacheOrTheRequestStaysInside(): void
    {
        $seen = [];
        set_error_handler(static function (int $number, string $text) use (&$seen): bool {
            $seen[] = $text;

            return true;
        });
        try {
            $result = ComposerAdvisories::forPackages(self::PACKAGES, static function (string $url) {
                // what a stream that cannot connect does: a warning, then false
                return file_get_contents(__DIR__ . '/no-such-file-for-the-advisory-test');
            });
            $this->assertSame([], $seen);
            $this->assertSame(['available' => false, 'advisories' => []], $result);

            // the handler that was active before the check is active again after it
            trigger_error('after the check', E_USER_WARNING);
            $this->assertSame(['after the check'], $seen);
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function aDamagedCacheEntryIsAMissAndItsWarningStaysInside(): void
    {
        ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(self::fixture()));
        $files = array_values(array_filter(
            (array) glob($this->cachePath . '/*' . ComposerAdvisories::CACHE_KEY_OK . '*'),
            static fn($file): bool => !str_contains((string) $file, ComposerAdvisories::CACHE_KEY_FAILED)
        ));
        $this->assertCount(1, $files, 'the good answer is stored in one file');

        // keep the expiry line, cut the stored answer short
        $stored = (string) file_get_contents((string) $files[0]);
        file_put_contents((string) $files[0], substr($stored, 0, (int) strpos($stored, "\n") + 1) . 'a:2:{s:9:"installed";');

        $seen = [];
        set_error_handler(static function (int $number, string $text) use (&$seen): bool {
            $seen[] = $text;

            return true;
        });
        try {
            // read on its own, the entry is no answer and the cache says so with a warning
            $this->assertIsNotArray(XoopsCache::read(ComposerAdvisories::CACHE_KEY_OK));
            $this->assertNotSame([], $seen, 'the damaged entry is one the cache reports');

            $seen           = [];
            $this->requests = [];
            $result         = ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(self::fixture()));

            $this->assertSame([], $seen);
            $this->assertCount(1, $this->requests, 'a damaged entry is a miss: the check asks again');
            $this->assertTrue($result['available']);
            $this->assertSame(['acme/in-range'], array_keys($result['advisories']));
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function aStoredAnswerIsCheckedAgainBeforeItIsPrinted(): void
    {
        ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(self::fixture()));
        $entry                                           = XoopsCache::read(ComposerAdvisories::CACHE_KEY_OK);
        $entry['available']                              = 'no';
        $entry['advisories']['acme/in-range'][0]['link']  = 'javascript:alert(1)';
        $entry['advisories']['acme/in-range'][0]['title'] = ['not', 'text'];
        $entry['advisories']['acme/in-range'][]           = 'not an advisory';
        $entry['advisories']['acme/clean']                = 'not a list';
        XoopsCache::write(ComposerAdvisories::CACHE_KEY_OK, $entry, 60);

        $result = ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(false));

        $this->assertSame(
            ['acme/in-range' => [['title' => '', 'link' => '', 'cve' => 'CVE-2026-00001', 'severity' => 'high']]],
            $result['advisories']
        );
        $this->assertCount(1, $this->requests);
    }

    #[Test]
    public function aRequestThatThrowsIsUnavailable(): void
    {
        $result = ComposerAdvisories::forPackages(self::PACKAGES, static function (string $url): string {
            throw new \RuntimeException('no route to host');
        });

        $this->assertSame(['available' => false, 'advisories' => []], $result);
    }

    #[Test]
    public function aChangedLibraryVersionAsksAgain(): void
    {
        ComposerAdvisories::forPackages(self::PACKAGES, $this->answering(self::fixture()));
        $updated = ComposerAdvisories::forPackages(
            [['name' => 'acme/in-range', 'version' => '1.2.5'], ['name' => 'acme/clean', 'version' => '1.0.0']],
            $this->answering(self::fixture())
        );

        $this->assertCount(2, $this->requests);
        $this->assertSame(['available' => true, 'advisories' => []], $updated);
    }

    #[Test]
    public function namesThatAreNotPackageNamesAreNeverSent(): void
    {
        $result = ComposerAdvisories::forPackages(
            [['name' => '../../etc/passwd', 'version' => '1.0'], ['name' => 'Acme/Upper', 'version' => '1.0'], ['version' => '1.0']],
            $this->answering(self::fixture())
        );

        $this->assertSame([], $this->requests);
        $this->assertSame(['available' => false, 'advisories' => []], $result);
    }

    #[Test]
    public function thePanelRowsCarryTheirAdvisories(): void
    {
        // the real class, for the type the panel asks for; this test has a process of its own
        require_once XOOPS_ROOT_PATH . '/class/template.php';
        require_once XOOPS_ROOT_PATH . '/modules/system/themes/ComposerInfo.php';
        $assigned = [];
        $template = $this->createMock(\XoopsTpl::class);
        $template->method('assign')->willReturnCallback(static function ($name, $value) use (&$assigned, $template) {
            $assigned[$name] = $value;

            return $template;
        });
        $answer = json_encode(['advisories' => ['smarty/smarty' => [[
            'title'            => 'Every release',
            'link'             => 'https://example.org/advisory',
            'cve'              => 'CVE-2026-00010',
            'affectedVersions' => '>=1.0',
            'severity'         => 'high',
        ]]]]);

        \ComposerInfo::getComposerInfo($template, $this->answering($answer));

        $marked = [];
        foreach ($assigned['composerPackages'] as $package) {
            $this->assertIsArray($package['advisories'], $package['name']);
            if ([] !== $package['advisories']) {
                $marked[$package['name']] = $package['advisories'][0]['cve'];
            }
        }
        $this->assertSame(['smarty/smarty' => 'CVE-2026-00010'], $marked);
        $this->assertTrue($assigned['composerAdvisoriesAvailable']);
        $this->assertSame(1, $assigned['composerAdvisoriesAffected']);
        $this->assertCount(1, $this->requests);
        $this->assertStringNotContainsString('version', $this->requests[0]);
    }

    /**
     * Render the package rows and the status line of one admin theme's panel.
     *
     * @param string               $file      template path below modules/system/themes
     * @param array<string, mixed> $variables
     *
     * @return string
     */
    private function renderPanel(string $file, array $variables): string
    {
        // what the template class reads when it is built
        defined('_LANGCODE') || define('_LANGCODE', 'en');
        defined('_TEXT_DIRECTION') || define('_TEXT_DIRECTION', 'ltr');
        defined('_RTL') || define('_RTL', false);
        $GLOBALS['xoopsConfig'] = ['debug_mode' => 0, 'template_set' => 'default', 'theme_set' => 'default', 'theme_fromfile' => 0]
            + (array) ($GLOBALS['xoopsConfig'] ?? []);
        require_once XOOPS_ROOT_PATH . '/class/template.php';
        $themes = XOOPS_ROOT_PATH . '/modules/system/themes';
        require_once $themes . '/transition/language/english/admin.php';
        require_once $themes . '/modern/language/english/main.php';

        $source = (string) file_get_contents($themes . '/' . $file);
        $found  = preg_match('/<\{foreach from=\$composerPackages.*?_COMPOSER_ADVISORIES_NONE\}>.*?<\{\/if\}>/s', $source, $part);
        $this->assertSame(1, $found, 'package rows and status line of ' . $file);

        $template = new \XoopsTpl();
        $template->setCompileDir($this->cachePath);
        $template->assign($variables);

        return $template->fetch('string:' . $part[0]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function panelTemplates(): array
    {
        return [
            'transition' => ['transition/xotpl/xo_accordion.tpl'],
            'default'    => ['default/xotpl/xo_accordion.tpl'],
            'modern'     => ['modern/xotpl/xo_dashboard.tpl'],
        ];
    }

    #[Test]
    #[DataProvider('panelTemplates')]
    public function thePanelPrintsAdvisoriesAsEscapedText(string $file): void
    {
        $html = $this->renderPanel($file, [
            'composerAdvisoriesAvailable' => true,
            'composerAdvisoriesAffected'  => 1,
            'composerPackages'            => [
                ['name' => 'acme/<b>one</b>', 'version' => '1.0"x', 'advisories' => [
                    ['title' => 'Title "><script>alert(1)</script>', 'link' => 'https://example.org/a?x=1&y=2', 'cve' => 'CVE-2026-00011', 'severity' => 'high'],
                    ['title' => 'No link <i>and</i> no CVE', 'link' => '', 'cve' => '', 'severity' => ''],
                ]],
                ['name' => 'acme/two', 'version' => '2.0.0', 'advisories' => []],
            ],
        ]);

        $this->assertStringContainsString('href="https://example.org/a?x=1&amp;y=2"', $html);
        $this->assertStringContainsString('>CVE-2026-00011</a>', $html);
        $this->assertStringContainsString('>No link &lt;i&gt;and&lt;/i&gt; no CVE</span>', $html);
        $this->assertStringContainsString('acme/&lt;b&gt;one&lt;/b&gt;', $html);
        $this->assertStringContainsString('1.0&quot;x', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<i>', $html);
        $this->assertSame(1, substr_count($html, '<a '));
        $this->assertStringNotContainsString('Advisory check unavailable', $html);
        $this->assertStringNotContainsString('No published security advisory', $html);
    }

    #[Test]
    #[DataProvider('panelTemplates')]
    public function thePanelSaysWhenNothingIsAffectedOrTheCheckIsUnavailable(string $file): void
    {
        $packages = [['name' => 'acme/two', 'version' => '2.0.0', 'advisories' => []]];

        $none = $this->renderPanel($file, ['composerAdvisoriesAvailable' => true, 'composerAdvisoriesAffected' => 0, 'composerPackages' => $packages]);
        $this->assertStringContainsString('No published security advisory covers an installed version', $none);
        $this->assertStringNotContainsString('Advisory check unavailable', $none);

        $unavailable = $this->renderPanel($file, ['composerAdvisoriesAvailable' => false, 'composerAdvisoriesAffected' => 0, 'composerPackages' => $packages]);
        $this->assertStringContainsString('Advisory check unavailable', $unavailable);
        $this->assertStringNotContainsString('No published security advisory', $unavailable);
    }

    #[Test]
    public function aGoodAnswerIsKeptLongerThanAFailedOne(): void
    {
        $this->assertSame(86400, ComposerAdvisories::TTL_OK);
        $this->assertSame(3600, ComposerAdvisories::TTL_FAILED);
    }
}
