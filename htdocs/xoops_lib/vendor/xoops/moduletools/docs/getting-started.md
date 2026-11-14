# Getting started with ModuleTools

ModuleTools (`Xoops\ModuleTools\*`) is the set of shared services that XOOPS modules used to
carry as copied `class/Common/*` files: install helpers, admin CRUD tables, confirmation
dialogs, breadcrumbs, letter navigation, sample-data buttons, permission helpers, version
checks and more. On XOOPS 2.8 it is bundled with Core, so a module simply calls it.

- [Installation](#installation)
- [The `Helper` is the entry point](#the-helper-is-the-entry-point)
- [Module install / update hooks](#module-install--update-hooks)
- [Version and dependency guards](#version-and-dependency-guards)
- [Admin pages](#admin-pages)
- [Objects and forms](#objects-and-forms)
- [Front-end helpers](#front-end-helpers)
- [Permissions](#permissions)
- [Sample data](#sample-data)
- [Language constants](#language-constants)
- [Everything else](#everything-else)

Migrating an existing module? Read [migrating-a-module.md](migrating-a-module.md) after this.

## Installation

**XOOPS 2.8.0+** ships the library in `xoops_lib/vendor/xoops/moduletools`; it is autoloaded on
every request. Nothing to install, nothing to activate — there is no `mtools` module row.

**Standalone / development:**

```bash
composer require xoops/moduletools
```

Requirements: PHP 8.4+, `xoops/xmf ^1.3`, `xoops/helpers ^1.0`. Smarty is Core's; the library
ships no templates.

**XOOPS 2.7.x** sites keep using the separately distributed mTools *module*; the class names
are the same (`XoopsModules\Mtools\*`), see [compatibility-policy.md](compatibility-policy.md).

## The `Helper` is the entry point

Almost every service takes the module's XMF helper:

```php
use Xmf\Module\Helper;

$helper = Helper::getHelper('mymodule');   // or your module's Helper subclass
$helper->dirname();                        // 'mymodule'
$helper->path('admin/index.php');
$helper->url('index.php');
$helper->getConfig('itemsperpage');
```

`ModuleContext` wraps the same things in a small value object and can define the legacy
`MYMODULE_URL` / `MYMODULE_PATH` / `MYMODULE_UPLOAD_PATH` … constants once, at bootstrap:

```php
use Xoops\ModuleTools\Module\ModuleContext;

$ctx = ModuleContext::fromHelper($helper);   // or ModuleContext::for('mymodule')
$ctx->uploadPath('images');                   // …/uploads/mymodule/images
$ctx->adminUrl('index.php');
$ctx->defineConstants();                      // keeps old code that reads MYMODULE_URL working
```

## Module install / update hooks

Describe the module's filesystem needs once in `config/config.php` (an array or object):

```php
<?php
// modules/mymodule/config/config.php
return [
    'name'            => 'mymodule',
    'uploadFolders'   => [XOOPS_UPLOAD_PATH . '/mymodule', XOOPS_UPLOAD_PATH . '/mymodule/images'],
    'copyBlankFiles'  => [XOOPS_UPLOAD_PATH . '/mymodule'],                 // gets an index.html
    'copyTestFolders' => [[__DIR__ . '/../testdata/images', XOOPS_UPLOAD_PATH . '/mymodule/images']],
    'templateFolders' => ['/templates/', '/templates/blocks/', '/templates/admin/'],
    'oldFiles'        => ['/class/oldhelper.php'],
    'oldFolders'      => ['/images'],
    'renameTables'    => ['mymodule_old' => 'mymodule_items'],
    'renameColumns'   => [],
    'moduleStats'     => ['items' => ['table' => 'mymodule_items']],
    'modCopyright'    => '<a href="https://xoops.org">XOOPS</a>',
];
```

Then the hooks shrink to:

```php
// include/oninstall.php
use Xoops\ModuleTools\Common\Configurator;
use Xoops\ModuleTools\Module\Installer;

function xoops_module_pre_install_mymodule(\XoopsModule $module): bool
{
    $helper = \Xmf\Module\Helper::getHelper($module->getVar('dirname'));
    Installer::prepare($module, Configurator::forModule($helper)); // upload folders + clean tables
    return true;
}

function xoops_module_install_mymodule(\XoopsModule $module): bool
{
    $helper = \Xmf\Module\Helper::getHelper($module->getVar('dirname'));
    Installer::install($module, Configurator::forModule($helper));  // blank files, test folders
    return true;
}
```

```php
// include/onupdate.php
function xoops_module_update_mymodule(\XoopsModule $module, ?int $previousVersion = null): bool
{
    $helper       = \Xmf\Module\Helper::getHelper($module->getVar('dirname'));
    $configurator = Configurator::forModule($helper);

    Installer::purgeHtmlTemplates($module);            // legacy .html templates
    Installer::removeOldAssets($module, $configurator); // oldFiles / oldFolders
    Installer::createUploadFolders($configurator);

    $migrate = new \Xoops\ModuleTools\Common\Migrate($configurator); // renameTables/renameColumns
    $migrate->synchronizeSchema();
    return true;
}
```

`Common\Migrate` extends `Xmf\Database\Migrate`; if you have no configurator-driven renames,
use `Xmf\Database\Migrate` directly.

Standard module preferences (social bookmarks, sample-data button, developer tools) are
appended to `xoops_version.php` in one line:

```php
\Xoops\ModuleTools\Common\StandardConfig::append($modversion, $moduleDirName);
// define _MI_MYMODULE_SOCIAL_BOOKMARKS(_DESC), _MI_MYMODULE_SHOW_SAMPLE_BUTTON(_DESC),
// _MI_MYMODULE_SHOW_DEV_TOOLS(_DESC) in language/english/modinfo.php
```

## Version and dependency guards

```php
use Xoops\ModuleTools\Common\VersionChecks;   // trait — use it in your Utility class
use Xoops\ModuleTools\Common\UpdateChecker;
use Xoops\ModuleTools\Module\Dependency;

// in include/oninstall.php / onupdate.php
if (!Utility::checkVerXoops($module) || !Utility::checkVerPhp($module)) {
    return false; // messages come from the module's min_xoops / min_php manifest entries
}

// "a newer release is available" box on the admin index
$update = UpdateChecker::checkVerModule($helper);       // reads $modversion['github_repo'],
                                                        // else XoopsModules25x/{dirname}
// another module must be installed
Dependency::requireModule('tag', '2.36', requireActive: true);   // throws on failure
$status = Dependency::checkModule('tag', '2.36');               // ['ok' => bool, 'errors' => [...]]
```

`ConsumerRuntime::guard(XOOPS_URL)` at the top of an entry point redirects with a readable
message when the ModuleTools API version the module was written against is not available.

## Admin pages

Server statistics and the standard admin "about" bits:

```php
use Xoops\ModuleTools\Common\ServerStats;   // trait
$adminObject->displayNavigation(basename(__FILE__));
echo Utility::getServerStats();              // GD, upload limits, PHP/MySQL versions …
```

Block management inside your own admin (instead of sending users to System → Blocks):

```php
$blocksadmin = new \Xoops\ModuleTools\Common\Blocksadmin($GLOBALS['xoopsDB'], $helper);
switch ($op) {
    case 'list':     $blocksadmin->listBlocks(); break;
    case 'edit':     $blocksadmin->editBlock($bid); break;
    case 'clone':    $blocksadmin->cloneBlock($bid); break;
    case 'delete':   $blocksadmin->deleteBlock($bid); break;                 // GET link carries a token
    case 'order':    $blocksadmin->orderBlock($bid, $oldtitle, /* … */ $bmodule); break;
    case 'edit_ok':  $blocksadmin->updateBlock($bid, $btitle, $bside, $bweight, $bvisible, $bcachetime, $bmodule, $options, $groups); break;
    case 'clone_ok': $blocksadmin->isBlockCloned($bid, $bside, $bweight, $bvisible, $bcachetime, $bmodule, $options, $groups); break;
}
```

Every write path (`order`, `edit_ok`, `clone_ok`, `delete`) validates the XOOPS security token
itself and fails closed. Do **not** call `$GLOBALS['xoopsSecurity']->check()` in your dispatcher
before delegating: `check()` consumes the one-time token, so a second check would reject the
request. The same rule applies to `ObjectController` and `TestdataSample`.

Generated CRUD tables for any `XoopsPersistableObjectHandler`:

```php
use Xoops\ModuleTools\Admin\{ObjectTable, ObjectColumn, ObjectController, SingleView, ObjectRow};

$handler = $helper->getHandler('Item');

// list
$table = new ObjectTable($handler, null, ['edit', 'delete']);
$table->addColumn(new ObjectColumn('title'));
$table->addColumn(new ObjectColumn('datesub', 'center', 120, 'getDateSub'));
$table->addFilter('status', 'getStatusList');       // method on the handler
$table->setDefaultSort('datesub'); $table->setDefaultOrder('DESC');
$table->render();

// save / delete from the object's default form
$controller = new ObjectController($handler);
$controller->storeFromDefaultForm(_AM_MYMODULE_ITEM_CREATED, _AM_MYMODULE_ITEM_MODIFIED, 'item.php');
$controller->handleObjectDeletion();

// read-only detail view
$view = new SingleView($item);
$view->addRow(new ObjectRow('title'));
$view->addRow(new ObjectRow('body', 'getBody'));
$view->render();

// CSV download
(new \Xoops\ModuleTools\Admin\Export($handler, $criteria, ['title', 'datesub']))->render('items.csv');
```

Module cloning ("create *mymodule2* from *mymodule*") — one admin page:

```php
// admin/clone.php
echo \Xoops\ModuleTools\Common\Cloner::handleAdminRequest($helper); // form on GET, clone on POST
```

## Objects and forms

`DynamicObject` is a `XoopsObject` that also knows how to render its own form; the object
metadata drives `ObjectFormBuilder`, `ObjectTable` and `SingleView`.

```php
use Xoops\ModuleTools\Object\DynamicObject;

class Item extends DynamicObject
{
    public function __construct()
    {
        $this->initVar('item_id', XOBJ_DTYPE_INT, null, false);
        $this->initVar('title',   XOBJ_DTYPE_TXTBOX, '', true, 255, '', '', _AM_MYMODULE_TITLE);
        $this->initVar('body',    XOBJ_DTYPE_TXTAREA, '', false, null, '', '', _AM_MYMODULE_BODY);
        $this->initVar('status',  XOBJ_DTYPE_INT, 1, false, null, '', '', _AM_MYMODULE_STATUS);
        $this->initVar('uid',     XOBJ_DTYPE_INT, 0, false, null, '', '', _AM_MYMODULE_AUTHOR);

        $this->setControl('body',   'textarea');        // or 'dhtmltextarea', 'yesno', 'user', 'select', 'image', …
        $this->setControl('status', ['name' => 'select', 'itemHandler' => 'item', 'method' => 'getStatusList']);
        $this->setControl('uid',    'user');
        $this->hideFieldFromForm('item_id');
        $this->setFieldForSorting('title');
        $this->initCommonVar('dohtml');                 // standard XOOPS flags: dohtml, dobr, doxcode, …
    }
}

$form = $item->getForm(_AM_MYMODULE_ITEM_EDIT, 'save', 'item.php'); // \XoopsThemeForm
$form->display();
```

`SeoObject` adds `meta_keywords` / `meta_description` fields (`initSeoVars()`);
`PersistableHandler` is the matching handler base with per-item permission helpers and an
upload configuration (`setUploaderConfig()`).

## Front-end helpers

```php
use Xoops\ModuleTools\Common\{Breadcrumb, LetterChoice, Confirm, Output, SocialBookmarks, Text};

// breadcrumb — renders db:mymodule_common_breadcrumb.tpl (register it in xoops_version.php)
$breadcrumb = new Breadcrumb($helper->dirname());
$breadcrumb->addLink(_MD_MYMODULE_HOME, $helper->url());
$breadcrumb->addLink($category->getVar('title'));
$xoopsTpl->assign('breadcrumb', $breadcrumb->render());

// A–Z navigation — renders db:mymodule_letterschoice.tpl
$letters = new LetterChoice($helper, $itemHandler, $criteria, 'title', [], 'letter', $helper->url('index.php'));
$xoopsTpl->assign('letterchoice', $letters->render());

// "Are you sure?" dialog, translated with _CO_MYMODULE_DELETE_* if you define them
$confirm = Confirm::forModule($helper, ['ok' => 1, 'id' => $id, 'op' => 'delete'], '', $item->getVar('title'));
$xoopsTpl->assign('form', $confirm->getFormConfirm()->render());

// meta tags, editor, sorting selector, share buttons, safe HTML excerpts
Output::metaKeywords($keywords);
Output::metaDescription($description);
$editor = Output::getEditor($helper, ['name' => 'body', 'value' => $body, 'rows' => 20]);
$xoopsTpl->assign('bookmarks', SocialBookmarks::render($url, $title));
$excerpt = Text::truncateHtml($body, 300);
```

Pagination: use the `render_pagination` Smarty plugin from `xoops/smartyextensions` for new
templates. `PaginationState` (`offset()`, `limitClause()`, `pages()`, `hasNext()` …) is the
PHP-side value object when you need the numbers; `Paginator` is the legacy HTML bar.

## Permissions

Item-level group permissions without touching `XoopsGroupPermHandler` directly:

```php
use Xoops\ModuleTools\Permission\ItemPermission;

$perm = ItemPermission::forModule($helper->getModule());
$perm->replace('mymodule_view', $catId, [XOOPS_GROUP_ADMIN, XOOPS_GROUP_USERS]);
$perm->isGranted('mymodule_view', $catId, $xoopsUser?->getGroups() ?? [XOOPS_GROUP_ANONYMOUS]);
$visibleCats = $perm->grantedItems('mymodule_view', $groups);
$perm->delete('mymodule_view', $catId);
```

In a `PersistableHandler` subclass, `setGrantedObjectsCriteria($criteria, 'mymodule_view')`
narrows any listing to what the current user may see.

## Sample data

Ship `testdata/english/*.yml` (one file per table, `Xmf\Database\TableLoad` format) and a
`testdata/index.php` that dispatches to `TestdataSample`:

```php
// testdata/index.php
$sample = new \Xoops\ModuleTools\Common\TestdataSample($helper);
match (\Xmf\Request::getCmd('op', '')) {
    'load'  => $sample->loadData(),
    'save'  => $sample->saveData(),
    'clear' => $sample->clearData(),
    default => null,
};
```

The admin buttons appear when the `displaySampleButton` preference (see `StandardConfig`) is on:

```php
// admin/index.php
\Xoops\ModuleTools\Common\TestdataButtons::loadButtonConfig($adminObject, $helper, $helper->getConfig('displaySampleButton'));
```

## Language constants

The library's own strings (`_CO_MTOOLS_*`) load automatically from its bundled English catalog.
Wherever a service shows text to the end user it first looks for **your** module's constant:

| Service | Looks for first | Falls back to |
|---|---|---|
| `Confirm` | `_CO_MYMODULE_DELETE_CONFIRM`, `_CO_MYMODULE_DELETE_LABEL` … | `_CO_MTOOLS_*` |
| `ModuleFeedback` | `_CO_MYMODULE_FB_*` | `_CO_MTOOLS_FB_*` |
| `ServerStats` | `_CO_MYMODULE_GDLIBSTATUS` … | `_CO_MTOOLS_*` |
| `StandardConfig` | `_MI_MYMODULE_SHOW_SAMPLE_BUTTON` (required) | — |

Define the module-specific ones in `language/<lang>/common.php` (or `modinfo.php` for `_MI_`)
when you want them translated; otherwise the English library text is used.

## Everything else

| Need | Class |
|---|---|
| Image resize / thumbnails | `Common\Resizer` + `ResizeRequest` / `ResizeResult` |
| Validated uploads | `Common\Media\MediaUploadRequest` |
| Directory / file health checks on the admin index | `Common\DirectoryChecker`, `Common\FileChecker` |
| Recursive copy/move/delete | `Common\FilesManagement` (trait) |
| SQL helpers with an explicit DB handle | `Common\Db` (`queryAndCheck`, `fieldExists`, `enumerate`, `cloneRecord`) |
| Category tree select boxes | `Common\ObjectTree` |
| Highlight search terms | `Common\Highlighter` |
| Module statistics for the admin "about" page | `Common\ModuleStats` (trait) |
| Autoload a module namespace without Composer | `Module\NamespaceAutoloader::register('XoopsModules\\Mymodule\\', __DIR__ . '/class')` |

`Common\SysUtility` / `Utility` bundle most of the above as one static facade for modules
that still call `Utility::truncateHtml()`-style helpers; new code should call the focused class.
