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

namespace xoopslogger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use XoopsLogger;

/**
 * Who receives logger output: the fatal-error screen and the in-page debug panel.
 *
 * The fatal screen showed its message and backtrace to every visitor with HTTP 200, and
 * render() dumped the panel to anyone whose request ended before common.php's debugLevel
 * check. Both now decide for themselves and fail closed.
 *
 * @category  Xoops
 * @package   Logger
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */
#[CoversClass(XoopsLogger::class)]
final class XoopsLoggerAudienceTest extends TestCase
{
    private const GLOBAL_KEYS = ['xoopsConfig', 'xoopsUser', 'xoopsUserIsAdmin'];

    /** @var array<string, array{bool, mixed}> */
    private array $savedGlobals = [];

    public static function setUpBeforeClass(): void
    {
        require_once XOOPS_ROOT_PATH . '/class/logger/xoopslogger.php';
    }

    protected function setUp(): void
    {
        foreach (self::GLOBAL_KEYS as $key) {
            $this->savedGlobals[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
            unset($GLOBALS[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedGlobals as $key => [$existed, $value]) {
            if ($existed) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    /**
     * @return array<string, array{array<string, mixed>|null, string, bool}>
     */
    public static function audienceProvider(): array
    {
        return [
            'config not loaded yet'       => [null, 'admin', false],
            'level 0, guest'              => [['debugLevel' => 0], 'guest', true],
            'level 1, guest'              => [['debugLevel' => 1], 'guest', false],
            'level 1, member'             => [['debugLevel' => 1], 'member', true],
            'level 2, member'             => [['debugLevel' => 2], 'member', false],
            'level 2, admin'              => [['debugLevel' => 2], 'admin', true],
            'level unset means 2, member' => [[], 'member', false],
            'level unset means 2, admin'  => [[], 'admin', true],
            'unknown level means 2, guest' => [['debugLevel' => 7], 'guest', false],
            'unknown level means 2, admin' => [['debugLevel' => 7], 'admin', true],
            'digit string is read, member' => [['debugLevel' => '1'], 'member', true],
            'empty string means 2, guest'  => [['debugLevel' => ''], 'guest', false],
            'typo means 2, guest'          => [['debugLevel' => 'O'], 'guest', false],
        ];
    }

    /**
     * @param array<string, mixed>|null $config
     */
    #[Test]
    #[DataProvider('audienceProvider')]
    public function theDebugLevelRuleDecidesWhoReceivesThePanel(?array $config, string $requester, bool $expected): void
    {
        $this->setRequest($config, $requester);

        self::assertSame($expected, (new XoopsLogger())->canReceiveDebugOutput());
    }

    #[Test]
    public function aSettledAnswerOutlivesLaterChangesToTheGlobals(): void
    {
        $this->setRequest(['debugLevel' => 2], 'admin');
        $logger = new XoopsLogger();
        self::assertTrue($logger->canReceiveDebugOutput(true));

        // A module page narrows $xoopsUserIsAdmin to its own admin right after common.php.
        $GLOBALS['xoopsUserIsAdmin'] = false;

        self::assertTrue($logger->canReceiveDebugOutput());
    }

    #[Test]
    public function anUnsettledAnswerFollowsTheGlobals(): void
    {
        $this->setRequest(['debugLevel' => 2], 'admin');
        $logger = new XoopsLogger();
        self::assertTrue($logger->canReceiveDebugOutput());

        $GLOBALS['xoopsUserIsAdmin'] = false;

        self::assertFalse($logger->canReceiveDebugOutput());
    }

    /**
     * The trigger seen on a test site: a preload redirected and exited during
     * auth.success, before common.php's check, and a guest got the query panel in the
     * 302 body.
     */
    #[Test]
    public function renderPassesThePageThroughUntouchedForARefusedRequester(): void
    {
        $this->setRequest(['debugLevel' => 2], 'guest');
        $logger = new XoopsLogger();
        // Active, as on a debug site: an inactive logger returns before the audience check.
        $logger->activated = true;
        $logger->addQuery('SELECT secret FROM xoops_users', null, null, 0.001);
        $page = '<html><body>Moved<!--{xo-logger-output}--></body></html>';

        self::assertSame($page, $logger->render($page));
        self::assertFalse($logger->activated);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedRequesterProvider(): array
    {
        return [
            'helper not loaded, error'     => ['guest', 'error'],
            'helper not loaded, exception' => ['guest', 'exception'],
            'not a developer, error'       => ['member', 'error'],
            'not a developer, exception'   => ['member', 'exception'],
            'the check throws'             => ['throwing', 'error'],
        ];
    }

    #[Test]
    #[DataProvider('refusedRequesterProvider')]
    public function theFatalScreenHidesTheDetailFromAnyoneButADeveloper(string $requester, string $entry): void
    {
        [$page, $result] = $this->runFatal($requester, $entry);

        self::assertStringContainsString('Error : Internal server error', $page);
        self::assertStringNotContainsString('MARKER-7f3a', $page);
        self::assertStringNotContainsString('Backtrace', $page);
        self::assertSame(500, $result['status']);
        // The registered loggers still get the real message.
        self::assertStringContainsString('MARKER-7f3a', implode("\n", $result['recorded']));
    }

    /**
     * debugLevel 0 lets anyone receive the panel, but the panel lists the stored error
     * message: a fatal for a non-developer must not render it.
     */
    #[Test]
    public function theDebugPanelDoesNotRestoreAHiddenFatalMessage(): void
    {
        [$page, $result] = $this->runFatal('member', 'error', 'panel');

        self::assertStringContainsString('Error : Internal server error', $page);
        self::assertStringNotContainsString('MARKER-7f3a', $page);
        self::assertStringNotContainsString('xo-logger-errors', $page);
        self::assertStringContainsString('MARKER-7f3a', implode("\n", $result['recorded']));
    }

    #[Test]
    public function aDeveloperFatalStillRendersThePanel(): void
    {
        [$page] = $this->runFatal('developer', 'error', 'panel');

        self::assertStringContainsString('xo-logger-errors', $page);
    }

    #[Test]
    public function aDeveloperStillSeesTheMessageAndTheBacktrace(): void
    {
        [$page, $result] = $this->runFatal('developer', 'error');

        self::assertStringContainsString('MARKER-7f3a secret detail', $page);
        self::assertStringContainsString('Backtrace', $page);
        self::assertSame(500, $result['status']);
    }

    /**
     * The requester gets the generic page, and a site without debug.php has no logger
     * registered: the PHP error log is then the only place that names the cause.
     */
    #[Test]
    #[DataProvider('refusedRequesterProvider')]
    public function aHiddenFatalIsWrittenToThePhpErrorLog(string $requester, string $entry): void
    {
        [, , $errorLog] = $this->runFatal($requester, $entry);

        self::assertStringContainsString('XOOPS fatal error: ', $errorLog);
        self::assertStringContainsString('MARKER-7f3a secret detail', $errorLog);
        if ('error' === $entry) {
            self::assertStringContainsString(' in /somefile.php on line 12', $errorLog);
        }
    }

    #[Test]
    public function theErrorLogEntryIsOneLineWithoutTheNotracePrefix(): void
    {
        [, , $errorLog] = $this->runFatal('guest', 'multiline');

        self::assertStringContainsString('XOOPS fatal error: MARKER-7f3a first forged entry in ', $errorLog);
        self::assertSame(0, substr_count(trim($errorLog), "\n"), $errorLog);
    }

    /**
     * A developer reads the message on the page: nothing is hidden, nothing is logged.
     */
    #[Test]
    public function aFatalShownToADeveloperIsNotWrittenToThePhpErrorLog(): void
    {
        [, , $errorLog] = $this->runFatal('developer', 'error');

        self::assertSame('', $errorLog);
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private function setRequest(?array $config, string $requester): void
    {
        if (null !== $config) {
            $GLOBALS['xoopsConfig'] = $config;
        }
        if ('guest' !== $requester) {
            $GLOBALS['xoopsUser'] = new \stdClass();
        }
        $GLOBALS['xoopsUserIsAdmin'] = 'admin' === $requester;
    }

    /**
     * @return array{string, array{status: int|false, recorded: list<string>}, string} the page,
     *                the runner's result and what the run wrote to the PHP error log
     */
    private function runFatal(string $requester, string $entry, string $panel = ''): array
    {
        // The run gets its own PHP error log: the default one in the CLI is stderr.
        $errorLogFile = (string) tempnam(sys_get_temp_dir(), 'xoops-fatal-log-');
        try {
            $command = escapeshellarg(PHP_BINARY)
                . ' -d ' . escapeshellarg('error_log=' . $errorLogFile)
                . ' ' . escapeshellarg(__DIR__ . '/fixtures/fatal-screen-runner.php')
                . ' ' . escapeshellarg($requester)
                . ' ' . escapeshellarg($entry)
                . ('' === $panel ? '' : ' ' . escapeshellarg($panel));
            $output   = (string) shell_exec($command);
            $errorLog = (string) file_get_contents($errorLogFile);
        } finally {
            unlink($errorLogFile);
        }

        $parts = explode("\n--RESULT--\n", $output, 2);
        self::assertCount(2, $parts, 'the runner did not finish: ' . $output);
        $result = json_decode($parts[1], true);
        self::assertIsArray($result, 'the runner did not return JSON: ' . $parts[1]);

        return [$parts[0], $result, $errorLog];
    }
}
