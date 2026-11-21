<?php
/**
 * Which stories the XML-RPC API hands to which account.
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

require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xoopsapi.php';

/**
 * A story that is not public goes to an administrator of the News module only.
 */
class XoopsXmlRpcStoryAccessTest extends TestCase
{
    /**
     * @return array<string, array{int|null, int|null, bool}> published, expired, readable by a member
     */
    public static function storyProvider(): array
    {
        $now = time();

        return [
            'published, no expiry'     => [$now - 3600, 0, true],
            'published, expires later' => [$now - 3600, $now + 3600, true],
            'awaiting approval'        => [0, 0, false],
            'scheduled for later'      => [$now + 3600, 0, false],
            'expired'                  => [$now - 7200, $now - 3600, false],
            'no such story'            => [null, null, false],
        ];
    }

    #[Test]
    #[DataProvider('storyProvider')]
    public function aStoryThatIsNotPublicGoesToAnAdministratorOnly(?int $published, ?int $expired, bool $public): void
    {
        $story = new class ($published, $expired) {
            public function __construct(private ?int $published, private ?int $expired) {}

            public function published(): ?int
            {
                return $this->published;
            }

            public function expired(): ?int
            {
                return $this->expired;
            }
        };

        $this->assertSame($public, $this->api(false)->_mayReadStory($story), 'an account with read access');
        $this->assertTrue($this->api(true)->_mayReadStory($story), 'an administrator of the module');
    }

    private function api(bool $administrator): \XoopsApi
    {
        $params   = [];
        $response = null;
        $module   = new class {
            public function getVar(string $name): int
            {
                return 7;
            }
        };
        $user = new class ($administrator) {
            public function __construct(private bool $administrator) {}

            public function isAdmin(int $moduleId): bool
            {
                return $this->administrator;
            }
        };
        $api = new \XoopsApi($params, $response, $module);
        $api->_setUser($user);

        return $api;
    }
}
