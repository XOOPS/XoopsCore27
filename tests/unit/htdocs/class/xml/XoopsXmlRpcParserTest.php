<?php
/**
 * The XML-RPC request parser.
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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/class/xml/rpc/xmlrpcparser.php';

/**
 * A request body as a client sends it parses to its method name and parameters,
 * whatever its layout, and without a notice.
 */
class XoopsXmlRpcParserTest extends TestCase
{
    private const EXPECTED = [
        '1',
        'admin',
        'p&ss"\'\\',
        ['title' => 'Hello world', 'count' => 3, 'tags' => ['a', 7], 'plain' => 'a & b'],
        true,
    ];

    /**
     * Parse a request body with every notice, warning and deprecation turned into an exception.
     *
     * @return array{bool, mixed, mixed}
     */
    private function parse(string $body): array
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): never {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
        );
        try {
            $parser = new \XoopsXmlRpcParser($body);
            $parsed = $parser->parse();

            return [$parsed, $parser->getMethodName(), $parser->getParam()];
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function aCompactRequestParsesToItsMethodAndParameters(): void
    {
        $body = '<?xml version="1.0"?><methodCall><methodName>metaWeblog.newPost</methodName><params>'
            . '<param><value><string>1</string></value></param>'
            . '<param><value><string>admin</string></value></param>'
            . '<param><value><string>p&amp;ss"\'\\</string></value></param>'
            . '<param><value><struct>'
            . '<member><name>title</name><value><string>Hello world</string></value></member>'
            . '<member><name>count</name><value><i4>3</i4></value></member>'
            . '<member><name>tags</name><value><array><data>'
            . '<value><string>a</string></value><value><int>7</int></value>'
            . '</data></array></value></member>'
            . '<member><name>plain</name><value>a &amp; b</value></member>'
            . '</struct></value></param>'
            . '<param><value><boolean>1</boolean></value></param>'
            . '</params></methodCall>';

        $this->assertSame([true, 'metaWeblog.newPost', self::EXPECTED], $this->parse($body));
    }

    #[Test]
    public function anIndentedRequestParsesToTheSameParameters(): void
    {
        // Line breaks and indentation around a typed value are layout, not part of the value
        $body = <<<'XML'
            <?xml version="1.0"?>
            <?client hint?>
            <methodCall>
              <methodName>metaWeblog.newPost</methodName>
              <params>
                <param>
                  <value>
                    <string>1</string>
                  </value>
                </param>
                <param>
                  <value>
                    <string>admin</string>
                  </value>
                </param>
                <param>
                  <value>
                    <string>p&amp;ss"'\</string>
                  </value>
                </param>
                <param>
                  <value>
                    <struct>
                      <member>
                        <name>title</name>
                        <value>
                          <string>Hello world</string>
                        </value>
                      </member>
                      <member>
                        <name>count</name>
                        <value>
                          <i4>3</i4>
                        </value>
                      </member>
                      <member>
                        <name>tags</name>
                        <value>
                          <array>
                            <data>
                              <value>
                                <string>a</string>
                              </value>
                              <value>
                                <int>7</int>
                              </value>
                            </data>
                          </array>
                        </value>
                      </member>
                      <member>
                        <name>plain</name>
                        <value>a &amp; b</value>
                      </member>
                    </struct>
                  </value>
                </param>
                <param>
                  <value>
                    <boolean>1</boolean>
                  </value>
                </param>
              </params>
            </methodCall>
            XML;

        $this->assertSame([true, 'metaWeblog.newPost', self::EXPECTED], $this->parse($body));
    }

    #[Test]
    public function anEmptyValueIsNullAndRaisesNoNotice(): void
    {
        $body = '<methodCall><methodName>xoops.getPost</methodName><params>'
            . '<param><value><struct><member><name>empty</name><value></value></member></struct></value></param>'
            . '</params></methodCall>';

        $this->assertSame([true, 'xoops.getPost', [['empty' => null]]], $this->parse($body));
    }

    #[Test]
    public function aUrlEncodedBodyIsNotARequest(): void
    {
        // What /xmlrpc.php handed the parser before 2.7.5: no request ever parsed
        [$parsed] = $this->parse(rawurlencode('<methodCall><methodName>a.b</methodName></methodCall>'));

        $this->assertFalse($parsed);
    }
}
