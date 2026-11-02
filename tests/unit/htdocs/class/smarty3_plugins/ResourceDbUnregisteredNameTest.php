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
 * The db: resource and template names it does not know.
 *
 * An unregistered name used to be read as a raw path, so a stored template
 * name (a cloned block's, for one) could name file:///etc/passwd or
 * mainfile.php and have it shown as a template. Only a .tpl/.html file inside
 * the XOOPS trees is read now.
 *
 * @category  Test
 * @package   Tests
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ResourceDbUnregisteredNameTest extends TestCase
{
    private const REAL_TEMPLATE = '/modules/system/templates/blocks/system_block_user.tpl';

    private static function templateFile(string $name): string
    {
        if (!class_exists('Smarty_Resource_Db', false)) {
            require_once XOOPS_ROOT_PATH . '/class/smarty3_plugins/resource.db.php';
        }

        return (new ReflectionMethod('Smarty_Resource_Db', 'templateFile'))->invoke(null, $name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function refused(): array
    {
        return [
            'stream uri'          => ['file://' . XOOPS_ROOT_PATH . self::REAL_TEMPLATE],
            'php wrapper'         => ['php://filter/resource=' . XOOPS_ROOT_PATH . self::REAL_TEMPLATE],
            'php file in root'    => [XOOPS_ROOT_PATH . '/mainfile.dist.php'],
            'outside the trees'   => [__FILE__],
            'missing tpl'         => [XOOPS_ROOT_PATH . '/modules/system/templates/no_such.tpl'],
            'relative name'       => ['no_such_registered.tpl'],
        ];
    }

    #[Test]
    #[DataProvider('refused')]
    public function anUnregisteredNameOutsideTheRulesHasNoSource(string $name): void
    {
        self::assertSame('', self::templateFile($name));
    }

    #[Test]
    public function aTemplateNamedLinkToAPhpFileHasNoSource(): void
    {
        // The link lives in the temp directory (the root may be read-only);
        // its target is inside the root, so only the extension check refuses it.
        $link = sys_get_temp_dir() . '/resource-db-test-' . bin2hex(random_bytes(4)) . '.tpl';
        if (!@symlink(XOOPS_ROOT_PATH . '/mainfile.dist.php', $link)) {
            self::markTestSkipped('symlinks are not available here');
        }
        try {
            self::assertSame('', self::templateFile($link));
        } finally {
            unlink($link);
        }
    }

    #[Test]
    public function aTemplateFileInsideTheRootIsStillRead(): void
    {
        $file = XOOPS_ROOT_PATH . self::REAL_TEMPLATE;
        self::assertFileExists($file);
        self::assertSame(realpath($file), self::templateFile($file));
    }
}
