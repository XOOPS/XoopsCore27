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

namespace {
    if (!class_exists('NewsStory', false)) {
        /**
         * Stands in for the News module's story class, which the XML-RPC API loads by name.
         */
        class NewsStory
        {
            /** @var array{published: int|null, expired: int|null} the story every instance holds */
            public static array $fixture = ['published' => null, 'expired' => null];

            public function __construct(public mixed $id = -1) {}

            public function published(): ?int
            {
                return self::$fixture['published'];
            }

            public function expired(): ?int
            {
                return self::$fixture['expired'];
            }

            public function uid(): int
            {
                return 3;
            }

            public function storyid(): int
            {
                return 42;
            }

            public function title(string $format = 'Show'): string
            {
                return 'Draft headline';
            }

            public function hometext(string $format = 'Show'): string
            {
                return 'Draft intro';
            }

            public function bodytext(string $format = 'Show'): string
            {
                return 'Draft body';
            }
        }
    }
}

namespace xoopsxml {

    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpctag.php';
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
            \NewsStory::$fixture = ['published' => $published, 'expired' => $expired];
            $story               = new \NewsStory();

            $this->assertSame($public, $this->api(false)->_mayReadStory($story), 'an account with read access');
            $this->assertTrue($this->api(true)->_mayReadStory($story), 'an administrator of the module');
        }

        #[Test]
        #[DataProvider('storyProvider')]
        public function getPostAnswersAMemberWithTheStoryOrWithFault111(?int $published, ?int $expired, bool $public): void
        {
            \NewsStory::$fixture = ['published' => $published, 'expired' => $expired];
            $response            = new \XoopsXmlRpcResponse();

            $returned = $this->getPost($this->api(false, $response));
            $sent     = $response->render();

            if ($public) {
                $this->assertSame('Draft headline', $returned['title']);
                $this->assertStringContainsString('Draft headline', $sent);
                $this->assertStringNotContainsString('<fault>', $sent);
            } else {
                $this->assertNull($returned);
                $this->assertStringContainsString('<name>faultCode</name><value>111</value>', $sent);
                $this->assertStringNotContainsString('Draft', $sent);
            }
        }

        #[Test]
        #[DataProvider('storyProvider')]
        public function getPostAnswersAnAdministratorWithAnyStory(?int $published, ?int $expired): void
        {
            \NewsStory::$fixture = ['published' => $published, 'expired' => $expired];
            $response            = new \XoopsXmlRpcResponse();

            $returned = $this->getPost($this->api(true, $response));
            $sent     = $response->render();

            $this->assertSame('Draft body', $returned['moretext']);
            $this->assertStringContainsString('Draft headline', $sent);
            $this->assertStringNotContainsString('<fault>', $sent);
        }

        #[Test]
        public function aRefusalIsTheFaultSentWhenAnotherApiAsksForTheStory(): void
        {
            // The MetaWeblog and Blogger APIs call getPost(false) and add fault 106 when it returns no array
            \NewsStory::$fixture = ['published' => 0, 'expired' => 0];
            $response            = new \XoopsXmlRpcResponse();

            $returned = $this->getPost($this->api(false, $response), false);
            $response->add(new \XoopsXmlRpcFault(106));

            $this->assertNull($returned);
            $this->assertStringContainsString('<name>faultCode</name><value>111</value>', $response->render());
        }

        /**
         * Call getPost() without the warning for the News module file, which no test site has.
         */
        private function getPost(\XoopsApi $api, bool $respond = true): ?array
        {
            set_error_handler(
                static fn (int $severity, string $message): bool => str_contains($message, 'class.newsstory.php'),
                E_WARNING
            );
            try {
                return $api->getPost($respond);
            } finally {
                restore_error_handler();
            }
        }

        private function api(bool $administrator, ?\XoopsXmlRpcResponse $response = null): \XoopsApi
        {
            // Story ID, user name, password: the account is set below, so the last two are not checked
            $params = [42, 'member', 'not-checked'];
            $module = new class {
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
}
