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

/**
 * MyTextSanitizer when xoops_data/configs/textsanitizer cannot be written.
 *
 * loadConfig() copies the shipped config.dist.php there on first use. When the copy
 * failed it returned an empty array, and array_filter($config['extensions']) then threw
 * a TypeError on every page showing BBCode or the DHTML editor.
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

require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';

#[CoversClass(MyTextSanitizer::class)]
#[CoversClass(MyTextSanitizerExtension::class)]
final class MyTextSanitizerConfigFallbackTest extends TestCase
{
    /** @var list<string> */
    private array $messages = [];

    /** Run $fn against a config folder that does not exist, collecting the PHP messages. */
    private function withoutConfigFolder(MyTextSanitizer $myts, callable $fn): mixed
    {
        $pathBefore        = $myts->path_config;
        $myts->path_config = sys_get_temp_dir() . '/myts-no-such-folder-' . bin2hex(random_bytes(6));
        $this->messages    = [];
        set_error_handler(function (int $no, string $msg): bool {
            $this->messages[] = $msg;

            return true;
        });
        try {
            return $fn();
        } finally {
            restore_error_handler();
            $myts->path_config = $pathBefore;
        }
    }

    /**
     * What a shipped *.dist.php returns. Read in its own scope: the file does
     * "return $config = [...]", which would overwrite a caller's $config.
     */
    private function shipped(string $file): array
    {
        return include XOOPS_ROOT_PATH . '/class/textsanitizer/' . $file;
    }

    #[Test]
    public function theMainConfigFallsBackToTheShippedDefaults(): void
    {
        $myts = new MyTextSanitizer();

        $loaded = $this->withoutConfigFolder($myts, fn() => $myts->loadConfig());

        self::assertNotEmpty($loaded['extensions'] ?? null);
        self::assertSame($this->shipped('config.dist.php'), $loaded);
        self::assertContains('Could not create textsanitizer config file config.php', $this->messages);
    }

    #[Test]
    public function anExtensionConfigFallsBackToItsShippedDefaults(): void
    {
        $myts = MyTextSanitizer::getInstance();

        $loaded = $this->withoutConfigFolder($myts, fn() => MyTextSanitizerExtension::loadConfig('image'));

        self::assertNotSame([], $loaded);
        self::assertSame($this->shipped('image/config.image.dist.php'), $loaded);
        self::assertContains('Could not create textsanitizer config file config.image.php', $this->messages);
    }

    #[Test]
    public function anExtensionWithoutShippedDefaultsGetsAnEmptyConfig(): void
    {
        $myts = MyTextSanitizer::getInstance();

        $loaded = $this->withoutConfigFolder($myts, fn() => MyTextSanitizerExtension::loadConfig('youtube'));

        self::assertSame([], $loaded);
    }

    #[Test]
    public function aConfigWithoutExtensionsRunsNone(): void
    {
        $myts         = new MyTextSanitizer();
        $myts->config = [];

        self::assertTrue($myts->executeExtensions());
    }
}
