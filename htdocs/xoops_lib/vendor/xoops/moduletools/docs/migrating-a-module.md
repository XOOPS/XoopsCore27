# Migrating an existing module to ModuleTools

This guide is for modules that carry their own copies of the mTools-era helper classes —
`class/Common/Configurator.php`, `Breadcrumb.php`, `Confirm.php`, `SysUtility.php`,
`TestdataButtons.php`, `ServerStats.php` … — or that depend on the separate **mTools module**
(`XoopsModules\Mtools\*`). Either way the target is the same: delete the copies, call the
library, keep behaviour.

Nothing here requires a rewrite. Every step is independently shippable, and a module that
stops after step 1 already works on XOOPS 2.8.

- [Step 0 — Decide the floor](#step-0--decide-the-floor)
- [Step 1 — Drop the mTools module dependency (zero code changes)](#step-1--drop-the-mtools-module-dependency-zero-code-changes)
- [Step 2 — Delete the copied `Common` classes](#step-2--delete-the-copied-common-classes)
- [Step 3 — Fix the dirname bug the copies all had](#step-3--fix-the-dirname-bug-the-copies-all-had)
- [Step 4 — Collapse the install/update hooks](#step-4--collapse-the-installupdate-hooks)
- [Step 5 — Replace the constant block with `ModuleContext`](#step-5--replace-the-constant-block-with-modulecontext)
- [Step 6 — Recipes for the remaining helpers](#step-6--recipes-for-the-remaining-helpers)
- [Step 7 — Verify](#step-7--verify)
- [Worked example](#worked-example)
- [What NOT to migrate yet](#what-not-to-migrate-yet)

## Step 0 — Decide the floor

| Your `min_xoops` | What to do |
|---|---|
| 2.8.0 or later | Follow this guide; ModuleTools is bundled with Core. |
| 2.7.x and 2.8 | Follow this guide **using the `XoopsModules\Mtools\*` names**, and keep the mTools module in your 2.7 install instructions. The aliases resolve to the library on 2.8 and to the module on 2.7. |
| 2.5.x | Keep your local copies; ModuleTools is PHP 8.4+. |

### 2.8-only recipe

When the module no longer supports XOOPS 2.7, declare the bundled-library floor
explicitly and remove the separate module dependency:

```php
// xoops_version.php
$modversion['min_php'] = '8.4';
$modversion['min_xoops'] = '2.8.0';
// Delete: $modversion['min_modules'] = ['mtools' => '...'];
```

Then use the direct library namespaces throughout the module. For example:

```php
use Xoops\ModuleTools\Common\Configurator;
use Xoops\ModuleTools\Module\Installer;
```

Delete any module-local `mtools` bootstrap or dependency shim. XOOPS 2.8
autoloads `Xoops\ModuleTools\*`; there is no `mtools` module to install or activate.

## Step 1 — Drop the mTools module dependency (zero code changes)

If the module already uses `XoopsModules\Mtools\Common\*`, nothing has to change on 2.8: the
library registers a lazy `class_alias` for every `XoopsModules\Mtools\X` → `Xoops\ModuleTools\X`.

Do remove anything that *checks for the module row*:

```php
// before — fails on 2.8 because there is no "mtools" module installed
if (!\xoops_isActiveModule('mtools')) { redirect_header(...); }

// after — checks the library API instead (works with the module on 2.7 too)
\Xoops\ModuleTools\Module\ConsumerRuntime::guard(XOOPS_URL);
```

and drop `'mtools'` from `$modversion['dependencies']` / any "requires mTools" text.

## Step 2 — Delete the copied `Common` classes

1. List what the module carries: `ls class/Common/`.
2. For each file that has a namesake in the library, delete the local copy and change the
   `use` line. Nothing else changes — the public API is identical to the 1.x mTools classes.

```php
// before
use XoopsModules\Mymodule\Common\Breadcrumb;
use XoopsModules\Mymodule\Common\Configurator;
use XoopsModules\Mymodule\Common\SysUtility;

// after
use Xoops\ModuleTools\Common\Breadcrumb;
use Xoops\ModuleTools\Common\Configurator;
use Xoops\ModuleTools\Common\SysUtility;
```

3. Keep a local class only if it genuinely differs. Typical keepers seen in practice:
   a `Text` that is the module's own *content* entity (not the HTML truncator), a
   forum-specific `ObjectTree`, a `Migrate` subclass with module-specific SQL. Rename such
   keepers so the name no longer collides (`ForumTree`, `SchemaMigration`).

4. `class/Utility.php` usually extends the local `SysUtility` and uses the local traits:

```php
// before
class Utility extends Common\SysUtility { use Common\VersionChecks; use Common\ServerStats; use Common\FilesManagement; }

// after
use Xoops\ModuleTools\Common\{SysUtility, VersionChecks, ServerStats, FilesManagement};
class Utility extends SysUtility { use VersionChecks; use ServerStats; use FilesManagement; }
```

## Step 3 — Fix the dirname bug the copies all had

Most copied classes derived the module directory with `basename(dirname(__DIR__, 2))` or
`basename(__DIR__)`. Inside `class/Common/` that resolved to `"Common"` — so
`_CO_MYMODULE_*` language constants were never found and English fallbacks were shown.

The library classes cannot guess your dirname, so **pass it**:

| Copy did | Library call |
|---|---|
| `new Confirm($hiddens, $action, $object)` | `Confirm::forModule($helper, $hiddens, $action, $object)` |
| `new Breadcrumb()` | `new Breadcrumb($helper->dirname())` |
| `new Configurator()` | `Configurator::forModule($helper)` (or `new Configurator($helper->path())`) |
| `ModuleFeedback::getInstance()` | `new ModuleFeedback(null, $helper->dirname())` |
| `Utility::getServerStats()` | unchanged — reads `$GLOBALS['xoopsModule']` |

`Xmf\Module\Helper` exposes the directory as **`$helper->dirname()`**. Some generated module
helpers add a `getDirname()` alias; the library never calls it, and neither should new code.

## Step 4 — Collapse the install/update hooks

Typical `include/oninstall.php` / `onupdate.php` copies are 100–200 lines of folder creation,
`index.html` copying, table dropping and old-asset removal. All of it is data in
`config/config.php`. The replacement is shown in full in
[getting-started.md → Module install / update hooks](getting-started.md#module-install--update-hooks).

Keep in the hook only what is module-specific: the `checkVerXoops`/`checkVerPhp` guard and
any default group permissions.

## Step 5 — Replace the constant block with `ModuleContext`

```php
// before (include/common.php or preloads/core.php) — 20–50 lines of this
define('MYMODULE_URL', XOOPS_URL . '/modules/' . $moduleDirName);
define('MYMODULE_PATH', XOOPS_ROOT_PATH . '/modules/' . $moduleDirName);
define('MYMODULE_UPLOAD_PATH', XOOPS_UPLOAD_PATH . '/' . $moduleDirName);
// …

// after
\Xoops\ModuleTools\Module\ModuleContext::for($moduleDirName)->defineConstants();
```

Existing code that reads `MYMODULE_UPLOAD_PATH` keeps working. New code should ask the
context (`$ctx->uploadPath()`) or `Xoops\Helpers\Service\Path::module($dirname, 'admin/x.php')`
and stop depending on constants.

## Step 6 — Recipes for the remaining helpers

**Filesystem.** The library keeps the XOOPS-specific behaviour (`createFolder()` also writes
the anti-listing `index.html`; `rrmdir()`/`rmove()`/`rcopy()` require an administrator).
Keep those calls where that behaviour is the contract; use `Xoops\Helpers\Utility\Filesystem`
where the caller already validates paths:

```php
FileChecker::copyFile($source, $destination, $allowedBase);   // containment enforced
\Xoops\Helpers\Utility\Filesystem::copy($source, $destination); // plain copy
```

**Schema.** `Common\Migrate` only adds the `renameTables` / `renameColumns` handling from
`config/config.php`. Without those, extend `Xmf\Database\Migrate` directly.

**Paths / URLs / prefs.** `ModuleContext` or the Helpers services:

```php
$path = \Xoops\Helpers\Service\Path::module($dirname, 'admin/index.php');
$url  = \Xoops\Helpers\Service\Url::module($dirname, 'admin/index.php');
$pref = \Xoops\Helpers\Service\Config::get($dirname . '.itemsperpage', 20);
```

**Database.** `SysUtility::queryAndCheck($db, $sql)` reads nothing global any more; prefer
`Common\Db::queryAndCheck($GLOBALS['xoopsDB'], $sql)` (same method, explicit handle).
`queryFAndCheck()` is deprecated — use `queryAndCheck()` for SELECTs and `$db->exec()` for writes.

**Templates the library renders.** Register them in `xoops_version.php` as before; the names
are unchanged: `mymodule_common_breadcrumb.tpl`, `mymodule_letterschoice.tpl`.

**Pagination.** Keep `Paginator` / `PaginationState` for now; see
[What NOT to migrate yet](#what-not-to-migrate-yet).

**Language.** Delete the `_CO_MYMODULE_*` strings that merely duplicated the mTools English
text (`_CO_MYMODULE_GDLIBSTATUS`, `_CO_MYMODULE_LOAD_SAMPLEDATA` …). The library supplies
them. Keep — or add — only the ones you actually translate.

## Step 7 — Verify

1. `php -l` every changed file, then load the admin index, the "about" page and one
   front page: every one of them exercises `Configurator`, `ServerStats`, `Breadcrumb`.
2. Grep for leftovers:
   ```bash
   grep -rn "Mymodule\\\\Common\\\\" .          # should be empty (or only the keepers from step 2)
   grep -rn "isActiveModule('mtools')" .
   grep -rn "basename(__DIR__)" class/         # the step-3 bug
   ```
3. Run the library's static-analysis stub against the module so mistakes surface without a
   running site — copy `stubs/xoops-core.stub` and `phpstan-bootstrap.php` from this
   repository into your module's `phpstan.neon` `scanFiles` / `bootstrapFiles`.
4. Install/update the module on a scratch site and go through create → edit → delete →
   permissions → sample data load/clear.

## Worked example

A 2.5-era content module (categories, items, ratings) with 14 copied `Common` classes:

| Before | After |
|---|---|
| `class/Common/*` — 14 files, ~2 900 lines | 0 files; `class/Utility.php` extends `Xoops\ModuleTools\Common\SysUtility` |
| `include/oninstall.php` — 140 lines | 25 lines: version guard + `Installer::prepare()` / `Installer::install()` + default permissions |
| `include/onupdate.php` — 210 lines | 40 lines: `Installer::purgeHtmlTemplates()`, `removeOldAssets()`, `Migrate->synchronizeSchema()` |
| `include/common.php` — 46 `define()` lines | `ModuleContext::for($dirname)->defineConstants()` |
| `_CO_MYMODULE_*` — 61 constants | 9 (the ones the module translates) |
| Delete dialog untranslated in German | Translated — `Confirm::forModule()` receives the dirname |

Diff size: −3 200 / +180 lines. Behaviour differences found in testing: none, plus the
dirname fix above.

## What NOT to migrate yet

- **`PaginationState` → `Xmf\Pagination\PaginatedResult`.** The two disagree on the zero-result
  page count (1 vs 0) and XMF has no band/SQL helpers. Split callers into retrieval, page
  metadata and rendering first; adopt the XMF result afterwards.
- **Admin CRUD (`ObjectTable`, `ObjectController`, `SingleView`).** Frozen compatibility API
  through XOOPS 2.8; the XMF presentation successor is not stable yet.
- **`DynamicObject` / `PersistableHandler` → XMF repositories.** A XOOPS 4 topic. The bridge
  exists in XMF 2 but switching the default handler before extracting forms, notifications
  and permissions from the objects is a regression.
- **`Breadcrumb` / `LetterChoice` → SmartyExtensions plugins.** Same freeze; the template
  contract stays as-is in 1.x.

See [compatibility-policy.md](compatibility-policy.md) for how long the frozen APIs are kept.
