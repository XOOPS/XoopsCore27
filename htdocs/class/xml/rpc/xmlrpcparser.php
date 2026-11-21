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

/**
 * @copyright    2000-2026 XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package
 * @since
 * @author       XOOPS Development Team, Kazumi Ono (AKA onokazu)
 */

if (!defined('XOOPS_ROOT_PATH')) {
    throw new \RuntimeException('XOOPS root path not defined');
}
require_once XOOPS_ROOT_PATH . '/class/xml/saxparser.php';
require_once XOOPS_ROOT_PATH . '/class/xml/xmltaghandler.php';

/**
 * Class RSS Parser
 *
 * This class offers methods to parse RSS Files
 *
 * @link          https://xoops.org/ Latest release of this class
 * @package       class
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @author        Kazumi Ono <onokazu@xoops.org>
 * @access        public
 */
class XoopsXmlRpcParser extends SaxParser
{
    /**
     * @access private
     * @var array
     */
    public $_param;

    /**
     * @access private
     * @var string
     */
    public $_methodName;

    /**
     * @access private
     * @var array
     */
    public $_tempName;

    /**
     * @access private
     * @var array
     */
    public $_tempValue;

    /**
     * @access private
     * @var array
     */
    public $_tempMember;

    /**
     * @access private
     * @var array
     */
    public $_tempStruct;

    /**
     * @access private
     * @var array
     */
    public $_tempArray;

    /**
     * @access private
     * @var array
     */
    public $_workingLevel = [];

    /**
     * @var bool true from the end of a value's type element to the next tag
     */
    private bool $valueTyped = false;

    /**
     * Constructor of the class
     * @access
     * @author
     * @see
     * @param $input
     */
    public function __construct($input)
    {
        parent::__construct($input);
        $this->addTagHandler(new RpcMethodNameHandler());
        $this->addTagHandler(new RpcIntHandler());
        $this->addTagHandler(new RpcDoubleHandler());
        $this->addTagHandler(new RpcBooleanHandler());
        $this->addTagHandler(new RpcStringHandler());
        $this->addTagHandler(new RpcDateTimeHandler());
        $this->addTagHandler(new RpcBase64Handler());
        $this->addTagHandler(new RpcNameHandler());
        $this->addTagHandler(new RpcValueHandler());
        $this->addTagHandler(new RpcMemberHandler());
        $this->addTagHandler(new RpcStructHandler());
        $this->addTagHandler(new RpcArrayHandler());
    }

    /**
     * A type element (<string>, <struct>, ...) opening inside a <value> means the text
     * read in that value so far was line breaks and indentation, not the value.
     *
     * @param \XMLParser $parser
     * @param string     $tagName
     * @param array      $attributesArray
     *
     * @return void
     */
    public function handleBeginElement($parser, $tagName, $attributesArray)
    {
        if ('value' !== $tagName && 'value' === end($this->tags)) {
            $this->resetTempValue();
        }
        $this->valueTyped = false;
        parent::handleBeginElement($parser, $tagName, $attributesArray);
    }

    /**
     * @param \XMLParser $parser
     * @param string     $tagName
     *
     * @return void
     */
    public function handleEndElement($parser, $tagName)
    {
        parent::handleEndElement($parser, $tagName);
        $this->valueTyped = 'value' !== $tagName && 'value' === end($this->tags);
    }

    /**
     * Text after a value's type element is layout as well: the value is already read.
     *
     * @param \XMLParser $parser
     * @param string     $data
     *
     * @return void
     */
    public function handleCharacterData($parser, $data)
    {
        if (!$this->valueTyped) {
            parent::handleCharacterData($parser, $data);
        }
    }

    /**
     * This Method starts the parsing of the specified RDF File. The File can be a local or a remote File.
     *
     * @param $name
     *
     * @return void
     */
    public function setTempName($name)
    {
        $this->_tempName[$this->getWorkingLevel()] = $name;
    }

    /**
     * @return mixed
     */
    public function getTempName()
    {
        return $this->_tempName[$this->getWorkingLevel()];
    }

    /**
     * @param $value
     */
    public function setTempValue($value)
    {
        if (is_array($value)) {
            $this->_tempValue = (array)$this->_tempValue;
            foreach ($value as $k => $v) {
                $this->_tempValue[$k] = $v;
            }
        } elseif (is_string($value)) {
            if (isset($this->_tempValue)) {
                if (is_string($this->_tempValue)) {
                    $this->_tempValue .= $value;
                }
            } else {
                $this->_tempValue = $value;
            }
        } else {
            $this->_tempValue = $value;
        }
    }

    /**
     * @return array
     */
    public function getTempValue()
    {
        return $this->_tempValue;
    }

    public function resetTempValue()
    {
        // null, not unset(): the next read of an unset property raises a warning
        $this->_tempValue = null;
    }

    /**
     * @param $name
     * @param $value
     */
    public function setTempMember($name, $value)
    {
        $this->_tempMember[$this->getWorkingLevel()][$name] = $value;
    }

    /**
     * @return mixed
     */
    public function getTempMember()
    {
        return $this->_tempMember[$this->getWorkingLevel()];
    }

    public function resetTempMember()
    {
        $this->_tempMember[$this->getCurrentLevel()] = [];
    }

    public function setWorkingLevel()
    {
        $this->_workingLevel[] = $this->getCurrentLevel();
    }

    /**
     * @return mixed
     */
    public function getWorkingLevel()
    {
        return $this->_workingLevel[count($this->_workingLevel) - 1];
    }

    public function releaseWorkingLevel()
    {
        array_pop($this->_workingLevel);
    }

    /**
     * @param $member
     */
    public function setTempStruct($member)
    {
        $key                                               = key($member);
        $this->_tempStruct[$this->getWorkingLevel()][$key] = $member[$key];
    }

    /**
     * @return mixed
     */
    public function getTempStruct()
    {
        return $this->_tempStruct[$this->getWorkingLevel()];
    }

    public function resetTempStruct()
    {
        $this->_tempStruct[$this->getCurrentLevel()] = [];
    }

    /**
     * @param $value
     */
    public function setTempArray($value)
    {
        $this->_tempArray[$this->getWorkingLevel()][] = $value;
    }

    /**
     * @return mixed
     */
    public function getTempArray()
    {
        return $this->_tempArray[$this->getWorkingLevel()];
    }

    public function resetTempArray()
    {
        $this->_tempArray[$this->getCurrentLevel()] = [];
    }

    /**
     * @param $methodName
     */
    public function setMethodName($methodName)
    {
        $this->_methodName = $methodName;
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->_methodName;
    }

    /**
     * @param $value
     */
    public function setParam($value)
    {
        $this->_param[] = $value;
    }

    /**
     * @return array
     */
    public function &getParam()
    {
        return $this->_param;
    }
}

/**
 * Class RpcMethodNameHandler
 */
class RpcMethodNameHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'methodName';
    }

    /**
     * @param $parser
     * @param $data
     */
    public function handleCharacterData($parser, &$data)
    {
        $parser->setMethodName($data);
    }
}

/**
 * Class RpcIntHandler
 */
class RpcIntHandler extends XmlTagHandler
{
    /**
     * @return array
     */
    public function getName()
    {
        return ['int', 'i4'];
    }

    /**
     * @param $parser
     * @param $data
     */
    public function handleCharacterData($parser, &$data)
    {
        $parser->setTempValue((int) $data);
    }
}

/**
 * Class RpcDoubleHandler
 */
class RpcDoubleHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'double';
    }

    /**
     * @param $parser
     * @param $data
     */
    public function handleCharacterData($parser, &$data)
    {
        $data = (float) $data;
        $parser->setTempValue($data);
    }
}

/**
 * Class RpcBooleanHandler
 */
class RpcBooleanHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'boolean';
    }

    /**
     * @param $parser
     * @param $data
     */
    public function handleCharacterData($parser, &$data)
    {
        $data = (bool) $data;
        $parser->setTempValue($data);
    }
}

/**
 * Class RpcStringHandler
 */
class RpcStringHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'string';
    }

    /**
     * @param $parser
     * @param $data
     */
    public function handleCharacterData($parser, &$data)
    {
        $parser->setTempValue((string) $data);
    }
}

/**
 * Class RpcDateTimeHandler
 */
class RpcDateTimeHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'dateTime.iso8601';
    }

    /**
     * @param $parser
     * @param $data
     */
    public function handleCharacterData($parser, &$data)
    {
        $matches = [];
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2}):(\d{2}):(\d{2})$/', $data, $matches)) {
            $parser->setTempValue(time());
        } else {
            $parser->setTempValue(gmmktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]));
        }
    }
}

/**
 * Class RpcBase64Handler
 */
class RpcBase64Handler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'base64';
    }

    /**
     * @param $parser
     * @param $data
     */
    public function handleCharacterData($parser, &$data)
    {
        $parser->setTempValue(base64_decode($data));
    }
}

/**
 * Class RpcNameHandler
 */
class RpcNameHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'name';
    }

    /**
     * @param $parser
     * @param $data
     */
    public function handleCharacterData($parser, &$data)
    {
        switch ($parser->getParentTag()) {
            case 'member':
                // The text of a name arrives in several pieces around an entity such as &amp;
                $parser->setTempName($parser->getTempName() . $data);
                break;
            default:
                break;
        }
    }
}

/**
 * Class RpcValueHandler
 */
class RpcValueHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'value';
    }

    /**
     * @param $parser
     * @param $data
     */
    public function handleCharacterData($parser, &$data)
    {
        // Text straight inside <value> is a value without a type element: a string
        switch ($parser->getParentTag()) {
            case 'param':
            case 'member':
                $parser->setTempValue($data);
                break;
            case 'data':
            case 'array':
                $parser->setTempValue($data);
                break;
            default:
                break;
        }
    }

    /**
     * @param $parser
     * @param $attributes
     */
    public function handleBeginElement($parser, &$attributes)
    {
        //$parser->resetTempValue();
    }

    /**
     * @param $parser
     */
    public function handleEndElement($parser)
    {
        // An empty value (<value></value>, <string/>) is the empty string
        $value = $parser->getTempValue() ?? '';
        switch ($parser->getCurrentTag()) {
            case 'member':
                $parser->setTempMember($parser->getTempName(), $value);
                break;
            case 'array':
            case 'data':
                $parser->setTempArray($value);
                break;
            default:
                $parser->setParam($value);
                break;
        }
        $parser->resetTempValue();
    }
}

/**
 * Class RpcMemberHandler
 */
class RpcMemberHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'member';
    }

    /**
     * @param $parser
     * @param $attributes
     */
    public function handleBeginElement($parser, &$attributes)
    {
        $parser->setWorkingLevel();
        $parser->resetTempMember();
        // Each member starts without a name: the previous member's name is not carried over
        $parser->setTempName('');
    }

    /**
     * @param $parser
     */
    public function handleEndElement($parser)
    {
        $member = $parser->getTempMember();
        $parser->releaseWorkingLevel();
        $parser->setTempStruct($member);
    }
}

/**
 * Class RpcArrayHandler
 */
class RpcArrayHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'array';
    }

    /**
     * @param $parser
     * @param $attributes
     */
    public function handleBeginElement($parser, &$attributes)
    {
        $parser->setWorkingLevel();
        $parser->resetTempArray();
    }

    /**
     * @param $parser
     */
    public function handleEndElement($parser)
    {
        $parser->setTempValue($parser->getTempArray());
        $parser->releaseWorkingLevel();
    }
}

/**
 * Class RpcStructHandler
 */
class RpcStructHandler extends XmlTagHandler
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'struct';
    }

    /**
     * @param $parser
     * @param $attributes
     */
    public function handleBeginElement($parser, &$attributes)
    {
        $parser->setWorkingLevel();
        $parser->resetTempStruct();
    }

    /**
     * @param $parser
     */
    public function handleEndElement($parser)
    {
        $parser->setTempValue($parser->getTempStruct());
        $parser->releaseWorkingLevel();
    }
}
