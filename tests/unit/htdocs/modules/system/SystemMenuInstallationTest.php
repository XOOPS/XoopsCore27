<?php

declare(strict_types=1);

namespace modulessystem;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SystemMenuInstallationTest extends TestCase
{
    private static bool $seedLoaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$seedLoaded) {
            require_once XOOPS_ROOT_PATH . '/modules/system/include/menu_seed.php';
            self::$seedLoaded = true;
        }
    }

    private function readSourceFile(string $relativePath): string
    {
        $fullPath = XOOPS_ROOT_PATH . '/' . $relativePath;
        $this->assertFileExists($fullPath, "Source file not found: {$relativePath}");

        $contents = file_get_contents($fullPath);
        $this->assertNotFalse($contents, "Unable to read source file: {$relativePath}");

        return $contents;
    }

    #[Test]
    public function sharedSeedDefinitionsExposeExpectedProtectedMenus(): void
    {
        $seed = system_menu_get_seed_definitions();

        $this->assertArrayHasKey('categories', $seed);
        $this->assertArrayHasKey('items', $seed);
        $this->assertArrayHasKey('home', $seed['categories']);
        $this->assertArrayHasKey('account', $seed['categories']);
        $this->assertArrayHasKey('admin', $seed['categories']);
        $this->assertNotEmpty($seed['items']);
        $this->assertSame('MENUS_HOME', $seed['categories']['home']['title']);
        $this->assertSame('MENUS_ACCOUNT', $seed['categories']['account']['title']);
        $this->assertSame('MENUS_ADMIN', $seed['categories']['admin']['title']);
        $this->assertCount(7, $seed['items']);
        $titles = array_column($seed['items'], 'title');
        $this->assertContains('MENUS_ACCOUNT_EDIT', $titles);
        $this->assertContains('MENUS_ACCOUNT_LOGIN', $titles);
        $this->assertContains('MENUS_ACCOUNT_REGISTER', $titles);
        $this->assertContains('MENUS_ACCOUNT_MESSAGES', $titles);
        $this->assertContains('MENUS_ACCOUNT_NOTIFICATIONS', $titles);
        $this->assertContains('MENUS_ACCOUNT_TOOLBAR', $titles);
        $this->assertContains('MENUS_ACCOUNT_LOGOUT', $titles);
        $this->assertContains('anonymous', $seed['categories']['account']['group_keys']);
        $this->assertContains('admin', $seed['categories']['admin']['group_keys']);
    }

    #[Test]
    public function sharedGroupKeyMapperResolvesKnownGroupsOnly(): void
    {
        $groupIds = system_menu_map_group_keys(
            ['admin', 'users', 'anonymous', 'missing'],
            [
                'admin' => 1,
                'users' => 2,
                'anonymous' => 3,
            ]
        );

        $this->assertSame([1, 2, 3], $groupIds);
    }

    #[Test]
    public function installerSchemaDeclaresMenuTablesUsingUnsignedIds(): void
    {
        $source = $this->readSourceFile('install/sql/mysql.structure.sql');

        $this->assertMatchesRegularExpression(
            '/CREATE TABLE menuscategory \\(.*?category_id int unsigned NOT NULL auto_increment/s',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/CREATE TABLE menusitems \\(.*?items_id int unsigned NOT NULL auto_increment.*?items_cid int unsigned NOT NULL default \\\'0\\\'/s',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/FOREIGN KEY \(items_cid\)\s+REFERENCES menuscategory \(category_id\)\s+ON DELETE CASCADE/s',
            $source
        );
    }

    #[Test]
    public function updateScriptCreateTablesMatchInstallerSignedness(): void
    {
        $source = $this->readSourceFile('modules/system/include/update.php');

        $this->assertMatchesRegularExpression(
            '/`category_id`\\s+INT UNSIGNED\\s+NOT NULL\\s+AUTO_INCREMENT/i',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/`items_id`\\s+INT UNSIGNED\\s+NOT NULL\\s+AUTO_INCREMENT/i',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/`items_cid`\\s+INT UNSIGNED\\s+NOT NULL\\s+DEFAULT 0/i',
            $source
        );
    }

    #[Test]
    public function installerUsesSharedSeedDefinitionsAndSeedsMenusAfterSystemModuleInsert(): void
    {
        $source = $this->readSourceFile('install/include/makedata.php');

        $this->assertStringContainsString(
            'system_menu_install_seed_defaults($dbm, $groups, 1);',
            $source
        );
    }

    #[Test]
    public function systemModuleRegistersInstallAndUpdateHooksToSameScript(): void
    {
        $modversion = [];
        require_once XOOPS_ROOT_PATH . '/modules/system/language/english/modinfo.php';
        include XOOPS_ROOT_PATH . '/modules/system/xoops_version.php';

        $this->assertSame('include/update.php', $modversion['onInstall'] ?? null);
        $this->assertSame('include/update.php', $modversion['onUpdate'] ?? null);
    }

    #[Test]
    public function updateScriptExposesMenuLifecycleFunctions(): void
    {
        require_once XOOPS_ROOT_PATH . '/modules/system/include/update.php';

        $this->assertTrue(function_exists('xoops_module_install_system'));
        $this->assertTrue(function_exists('xoops_module_update_system'));
        $this->assertTrue(function_exists('system_menu_seed_defaults'));
    }
}
