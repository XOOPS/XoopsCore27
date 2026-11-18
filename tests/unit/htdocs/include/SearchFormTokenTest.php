<?php
/**
 * Tests for the search form built by include/searchform.php
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
 * @package   core
 * @since     2.7.5
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The search form submits by GET, so every field it holds ends up in the URL.
 * search.php checks no security token; one in the form would only put a live
 * token into browser history, server logs and Referer headers.
 *
 * Runs in its own process: the form needs the _SR_ texts of the search language
 * file, and a defined constant cannot be removed again for the tests that follow.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SearchFormTokenTest extends TestCase
{
    /**
     * Include the form file the way search.php does, with the variables it reads.
     *
     * @return \XoopsThemeForm
     */
    private function buildForm(): \XoopsThemeForm
    {
        require_once XOOPS_ROOT_PATH . '/language/english/search.php';

        $queries           = ['xoops'];
        $andor             = 'AND';
        $mid               = 0;
        $mids              = [];
        $xoopsConfigSearch = ['keyword_min' => 3];
        // a loaded module list keeps the file away from the module handler and the database
        $modules = [
            2 => new class {
                /**
                 * @param string $key
                 *
                 * @return string
                 */
                public function getVar($key)
                {
                    return 'news';
                }
            },
        ];

        include XOOPS_ROOT_PATH . '/include/searchform.php';

        /** @var \XoopsThemeForm $search_form */
        return $search_form;
    }

    #[Test]
    public function theGetFormCarriesNoSecurityToken(): void
    {
        $form = $this->buildForm();

        $this->assertSame('get', $form->getMethod());
        foreach ($form->getElements(true) as $element) {
            $this->assertNotInstanceOf(
                \XoopsFormHiddenToken::class,
                $element,
                'a token in a GET form is written into the search URL'
            );
        }
    }

    #[Test]
    public function theFormKeepsItsSearchFields(): void
    {
        $names = [];
        foreach ($this->buildForm()->getElements(true) as $element) {
            $names[] = $element->getName(false);
        }

        $this->assertSame(['query', 'andor', 'mids[]', '', 'action', 'submit'], $names);
    }
}
