<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.
*/

/**
 * @copyright 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author    XOOPS Development Team
 */

use Xoops\ModuleTools\Internal\PackageLanguage;

/**
 * Class LetterChoice
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 */
class LetterChoice
{
    public $helper;
    private $criteria;
    private $field_name;
    private $alphabet;
    private $url;
    private $extra = '';
    /**
     * *#@-
     * @param mixed      $modHelper
     * @param mixed      $objHandler
     * @param null|mixed $criteria
     * @param null|mixed $field_name
     * @param mixed      $arg_name
     * @param null|mixed $url
     * @param mixed      $extra_arg
     * @param mixed      $caseSensitive
     */

    /**
     * Constructor
     *
     * @param                                $modHelper
     * @param \XoopsPersistableObjectHandler $objHandler {@link \XoopsPersistableObjectHandler}
     * @param null                           $criteria   {@link \CriteriaElement}
     * @param null                           $field_name search by field
     * @param array                          $alphabet   array of alphabet letters
     * @param string                         $arg_name   item on the current page
     * @param null                           $url
     * @param string                         $extra_arg  Additional arguments to pass in the URL
     * @param bool                           $caseSensitive
     */
    public function __construct(
        /**
         * *#@+
         */
        public $modHelper,
        private $objHandler,
        $criteria = null,
        $field_name = null,
        array $alphabet = [],
        private $arg_name = 'letter',
        $url = null,
        $extra_arg = '',
        private $caseSensitive = false
    ) {
        $this->criteria   = $criteria ?? new \CriteriaCompo();
        $this->field_name = $field_name ?? $this->objHandler->identifierName;
        //        $this->alphabet   = (count($alphabet) > 0) ? $alphabet : range('a', 'z'); // is there a way to get locale alphabet?
        //        $this->alphabet       = getLocalAlphabet();
        $config       = self::runtimeGlobal('xoopsConfig');
        $language     = is_array($config) ? (string) ($config['language'] ?? 'english') : 'english';
        PackageLanguage::load('common', $language);
        $languageFile = $this->modHelper->path('language/' . $language . '/alphabet.php');
        if (!is_file($languageFile)) {
            $languageFile = $this->modHelper->path('language/english/alphabet.php');
        }
        // A caller-supplied alphabet wins; otherwise the module's language/<lang>/alphabet.php, then a-z.
        $this->alphabet = [] !== $alphabet ? $alphabet : (is_file($languageFile) ? require $languageFile : range('a', 'z'));
        $this->url      = $url ?? $_SERVER['SCRIPT_NAME'];
        if ('' !== $extra_arg && '&amp;' !== \mb_substr($extra_arg, -5) && '&' !== \mb_substr($extra_arg, -1)) {
            $this->extra = '&amp;' . $extra_arg;
        }
    }

    /**
     * Create choice by letter
     *
     * @param null|int $alphaCount
     * @param null|int $howmanyother
     */
    public function render($alphaCount = null, $howmanyother = null): string
    {
        $moduleDirName      = $this->modHelper->dirname();
        $moduleDirNameUpper = \mb_strtoupper($moduleDirName);
        \xoops_loadLanguage('common', $moduleDirName);
        \xoops_loadLanguage('alphabet', $moduleDirName);
        $all   = \constant('_CO_MTOOLS_ALL');
        $other = \constant('_CO_MTOOLS_OTHER');

        $ret = '';

        if (!$this->caseSensitive) {
            $this->criteria->setGroupBy('UPPER(LEFT(' . $this->field_name . ',1))');
        } else {
            $this->criteria->setGroupBy('LEFT(' . $this->field_name . ',1)');
        }
        $countsByLetters = $this->objHandler->getCounts($this->criteria);
        // fill alphabet array
        $alphabetArray = [];
        $letter_array  = [];

        $letter                 = 'All';
        $letter_array['letter'] = $all;
        $letter_array['count']  = $alphaCount;
        $letter_array['url']    = $this->url;
        $alphabetArray[$letter] = $letter_array;

        foreach ($this->alphabet as $letter) {
            $letter_array           = [];
            $letter_array['letter'] = $letter;
            if (!$this->caseSensitive) {
                if (isset($countsByLetters[\mb_strtoupper($letter)])) {
                    $letter_array['count'] = $countsByLetters[\mb_strtoupper($letter)];
                    $letter_array['url']   = $this->url . '?' . $this->arg_name . '=' . $letter . $this->extra;
                } else {
                    $letter_array['count'] = 0;
                    $letter_array['url']   = '';
                }
            } else {
                if (isset($countsByLetters[$letter])) {
                    $letter_array['count'] = $countsByLetters[$letter];
                    $letter_array['url']   = $this->url . '?' . $this->arg_name . '=' . $letter . $this->extra;
                } else {
                    $letter_array['count'] = 0;
                    $letter_array['url']   = '';
                }
            }
            $alphabetArray[$letter] = $letter_array;
            unset($letter_array);
        }

        // Its own key: reusing $letter here overwrote the last letter of the alphabet ("Z" vanished).
        $letter_array['letter'] = $other;
        $letter_array['count']  = $howmanyother;
        $letter_array['url']    = $this->url . '?' . $this->arg_name . '=Other' . $this->extra;
        $alphabetArray['Other'] = $letter_array;

        // render output
        $theme = self::runtimeGlobal('xoTheme');
        $xoops = self::runtimeGlobal('xoops');
        if (!is_object($theme)) {
            require_once $xoops->path('/class/theme.php');
            self::setRuntimeGlobal('xoTheme', new \xos_opal_Theme());
        }
        require_once $xoops->path('/class/template.php');
        $choiceByLetterTpl          = new \XoopsTpl();
        $choiceByLetterTpl->setCaching(\Smarty\Smarty::CACHING_OFF);
        $choiceByLetterTpl->assign('alphabet', $alphabetArray);
        $ret .= $choiceByLetterTpl->fetch("db:{$this->modHelper->dirname()}_letterschoice.tpl");
        unset($choiceByLetterTpl);

        return $ret;
    }

    /** @legacy-global-accessor */
    private static function runtimeGlobal(string $name): mixed
    {
        return $GLOBALS[$name] ?? null;
    }

    /** @legacy-global-accessor */
    private static function setRuntimeGlobal(string $name, mixed $value): void
    {
        $GLOBALS[$name] = $value;
    }
}
