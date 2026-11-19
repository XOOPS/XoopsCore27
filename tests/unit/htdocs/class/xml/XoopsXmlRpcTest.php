<?php
/**
 * The XML-RPC date tag and the credential check of the XML-RPC API.
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Tests
 * @package   class
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

declare(strict_types=1);

namespace xoopsxml;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpctag.php';
require_once XOOPS_ROOT_PATH . '/kernel/user2fa.php';

/**
 * The XML-RPC date tag and the credential check every XML-RPC method starts with.
 */
class XoopsXmlRpcTest extends TestCase
{
    private const SANDBOX = __NAMESPACE__ . '\XmlRpcApiSandbox';

    /** @var array<string, mixed> */
    private array $globals = [];

    protected function setUp(): void
    {
        foreach (['xoopsConfig', 'xmlRpcTestHandlers'] as $key) {
            $this->globals[$key] = $GLOBALS[$key] ?? null;
        }
        // No site-wide second factor: the check under test is the name and password.
        $GLOBALS['xoopsConfig'] = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->globals as $key => $value) {
            if (null === $value) {
                unset($GLOBALS[$key]);
            } else {
                $GLOBALS[$key] = $value;
            }
        }
    }

    #[Test]
    public function datetimeRendersIso8601InUtcWithoutADeprecatedCall(): void
    {
        // gmstrftime() is deprecated as of PHP 8.1
        set_error_handler(
            static function (int $severity, string $message): never {
                throw new \ErrorException($message, 0, $severity);
            },
            E_DEPRECATED
        );
        try {
            $rendered = (new \XoopsXmlRpcDatetime(1700000000))->render();
            $fromText = (new \XoopsXmlRpcDatetime('2023-11-14 22:13:20 UTC'))->render();
        } finally {
            restore_error_handler();
        }

        $expected = '<value><dateTime.iso8601>20231114T22:13:20</dateTime.iso8601></value>';
        $this->assertSame($expected, $rendered);
        $this->assertSame($expected, $fromText);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function credentialProvider(): array
    {
        return [
            'plain' => ['admin', 'secret'],
            'quotes and a backslash' => ["o'brien", 'pa"ss\\wo\'rd'],
            'surrounding spaces' => ['admin', ' secret '],
        ];
    }

    #[Test]
    #[DataProvider('credentialProvider')]
    public function checkUserPassesTheNameAndPasswordAsSent(string $name, string $password): void
    {
        $api = $this->api();

        $this->assertTrue($api->_checkUser($name, $password));
        $this->assertSame([[$name, $password]], $GLOBALS['xmlRpcTestHandlers']['member']->calls);
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public static function refusedCredentialProvider(): array
    {
        return [
            'password with a NUL byte' => ['admin', "secret\0tail"],
            'array password' => ['admin', ['secret']],
            'array name' => [['admin'], 'secret'],
        ];
    }

    #[Test]
    #[DataProvider('refusedCredentialProvider')]
    public function checkUserRefusesWhatCannotBeACredential(mixed $name, mixed $password): void
    {
        $api = $this->api();

        $this->assertFalse($api->_checkUser($name, $password));
        $this->assertSame([], $GLOBALS['xmlRpcTestHandlers']['member']->calls);
    }

    /**
     * XoopsXmlRpcApi loaded into a namespace of its own, where xoops_getHandler()
     * returns the stubs below instead of the kernel handlers.
     */
    private function api(): object
    {
        $user = new class {
            public function isActive(): bool
            {
                return true;
            }

            public function getVar(string $name): int
            {
                return 7;
            }

            /** @return int[] */
            public function getGroups(): array
            {
                return [2];
            }
        };
        $GLOBALS['xmlRpcTestHandlers'] = [
            'member' => new class($user) {
                /** @var list<array{mixed, mixed}> */
                public array $calls = [];

                public function __construct(private object $user) {}

                public function loginUser(mixed $name, mixed $password): object
                {
                    $this->calls[] = [$name, $password];

                    return $this->user;
                }
            },
            'user2fa' => new class {
                public function stateFor(int $uid): string
                {
                    return \XoopsUser2faHandler::STATE_NONE;
                }
            },
            'groupperm' => new class {
                /** @param int[] $groups */
                public function checkRight(string $name, int $id, array $groups): bool
                {
                    return true;
                }
            },
        ];

        $class = self::SANDBOX . '\XoopsXmlRpcApi';
        if (!class_exists($class, false)) {
            $source = file_get_contents(XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpcapi.php');
            $this->assertIsString($source);
            $this->assertStringStartsWith('<?php', $source);
            class_alias(\XoopsUser2faHandler::class, self::SANDBOX . '\XoopsUser2faHandler');
            eval(
                'namespace ' . self::SANDBOX . ';'
                . ' function xoops_getHandler($name) { return $GLOBALS["xmlRpcTestHandlers"][$name]; }'
                . substr($source, 5)
            );
        }
        $params = [];
        $response = new \stdClass();
        $module = new class {
            public function getVar(string $name): int
            {
                return 1;
            }
        };

        return new $class($params, $response, $module);
    }
}
