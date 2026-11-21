<?php
/**
 * XOOPS XMLRPC
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             core
 * @since               2.0.0
 * @deprecated          since XOOPS 2.7.5: XML-RPC will be removed in XOOPS 2.8.0
 */

define('XOOPS_XMLRPC', 1);

include __DIR__ . '/mainfile.php';

/**
 * XML-RPC is disabled by default for security, and deprecated: it will be removed in XOOPS 2.8.0.
 *
 * To enable it, add the following to XOOPS_VAR_PATH/configs/xoopsconfig.php:
 *   'xmlrpc_enabled' => true,
 */
if (empty($xoopsConfig['xmlrpc_enabled'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("XML-RPC is disabled. It is deprecated and will be removed in XOOPS 2.8.0. To enable it until then, set 'xmlrpc_enabled' => true in XOOPS_VAR_PATH/configs/xoopsconfig.php");
}

// Recorded by the debug log of a site that still uses the endpoint
$GLOBALS['xoopsLogger']->addDeprecated('XML-RPC (xmlrpc.php) is deprecated since XOOPS 2.7.5 and will be removed in XOOPS 2.8.0');

error_reporting(0);

include_once $GLOBALS['xoops']->path('class/xml/rpc/xmlrpctag.php');
include_once $GLOBALS['xoops']->path('class/xml/rpc/xmlrpcparser.php');

$GLOBALS['xoopsLogger']->activated = false;

$response = new XoopsXmlRpcResponse();
// The body is the XML document: URL-encoding it, as was done here until 2.7.5, left nothing to parse
$parser   = new XoopsXmlRpcParser((string) file_get_contents('php://input'));
if (!$parser->parse()) {
    $response->add(new XoopsXmlRpcFault(102));
} else {
    /** @var XoopsModuleHandler $module_handler */
    $module_handler = xoops_getHandler('module');
    $module         = $module_handler->getByDirname('news');
    if (!is_object($module)) {
        $response->add(new XoopsXmlRpcFault(110));
    } else {
        $methods = explode('.', (string) $parser->getMethodName());
        switch ($methods[0]) {
            case 'blogger':
                include_once $GLOBALS['xoops']->path('class/xml/rpc/bloggerapi.php');
                $rpc_api = new BloggerApi($parser->getParam(), $response, $module);
                break;
            case 'metaWeblog':
                include_once $GLOBALS['xoops']->path('class/xml/rpc/metaweblogapi.php');
                $rpc_api = new MetaWeblogApi($parser->getParam(), $response, $module);
                break;
            case 'mt':
                include_once $GLOBALS['xoops']->path('class/xml/rpc/movabletypeapi.php');
                $rpc_api = new MovableTypeApi($parser->getParam(), $response, $module);
                break;
            case 'xoops':
            default:
                include_once $GLOBALS['xoops']->path('class/xml/rpc/xoopsapi.php');
                $rpc_api = new XoopsApi($parser->getParam(), $response, $module);
                break;
        }
        $method = $methods[1] ?? '';
        // Only the API methods are callable: the helpers of the API classes start with an underscore
        if ('' === $method || '_' === $method[0] || !method_exists($rpc_api, $method)) {
            $response->add(new XoopsXmlRpcFault(107));
        } else {
            $rpc_api->$method();
        }
    }
}
$payload = $response->render();
header('Server: XOOPS XML-RPC Server');
header('Content-type: text/xml');
header('Content-Length: ' . strlen($payload));
echo $payload;
