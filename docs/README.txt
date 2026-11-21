XOOPS 2.7.5 Beta 2

The XOOPS Development Team is pleased to announce XOOPS 2.7.5 Beta 2, the
second test release of XOOPS 2.7.5. This is a maintenance release: it collects
the bug fixes and security hardening made since XOOPS 2.7.4 and adds a
light/dark toggle to the Default and Transition admin themes. XOOPS 2.7.5 runs
on PHP 8.2 through 8.5.

This is a beta: more fixes may follow before the final release. Beta 2 adds
the fixes made since Beta 1 (see "Changes since Beta 1" below), a security
advisory check for the bundled libraries, and updated libraries. Please test
it on a staging copy of your site, not on a production site, and report
anything you find.

Admin themes: Default and Transition have a light/dark toggle that remembers
your choice. Default adds dark styling for module panels, permission grids and
SCEditor. The standalone Dark theme is removed; a site that had selected it
loads Transition, where the header toggle switches dark mode on.

Download XOOPS 2.7.5 Beta 2 from GitHub: https://github.com/XOOPS/XoopsCore27/releases

For full documentation on installing or upgrading XOOPS please see:
https://xoops.github.io/xoops-docs/

Changes since Beta 1
-----------------------------------
- Control panel: the Composer Packages panel marks every bundled library whose
  installed version is covered by a published security advisory, with a link
  to the advisory. It asks Packagist at most once a day and sends the package
  names only.
- Error pages: the fatal-error page shows the message and backtrace only to a
  webmaster while debugging is enabled, and is sent with HTTP 500 instead of
  200. The debug panel and the Smarty debug console follow the debugLevel
  setting on every page, including one that a preload ends early.
- Sign-in accepts the password exactly as it was set: a password with a
  leading or trailing space, or with angle brackets, could be set but not used
  to sign in.
- xoops_data/.htaccess works on Apache 2.4 without mod_access_compat, where
  the old file caused HTTP 500 instead of 403. The shipped directories that
  had no index file get one, so Apache no longer lists their contents.
- Every page sends X-Content-Type-Options: nosniff, and the search form no
  longer puts security tokens into the search URL.
- Upgrade wizard: it asks you to confirm a current backup before the first
  patch and clears the caches again after the last one. The Smarty 4 template
  repair keeps the original template until the repaired version is complete.
  The older patches (2.0.18 to 2.5.11) no longer stop with PHP 8 errors, and
  the character set conversion keeps empty-string column defaults.
- Clearing the caches keeps the .htaccess, .user.ini, php.ini and web.config
  files a site has placed in the cache folders.
- The TinyMCE 5 and 7 image manager can delete images and categories again,
  and removes an image's file only when it lies inside the uploads folder.
- Comments: links keep a module's extra parameters intact, and a new comment
  whose thread cannot be saved shows a message instead of a PHP error.
- The file cache's garbage collection removes an entry once its own expiry has
  passed, and the cache 'lock' setting no longer stops reads with a TypeError.
- The FineUploader endpoint reports I/O failures with an HTTP error status
  instead of success, and keeps the chunks for a retry.
- The installer's path page checks again that mainfile.php, uploads and the
  xoops_data folders are writable, and no longer widens permissions to 0777.
- Text: a sanitizer extension that cannot be loaded (such as flash, removed in
  2.7.0) is skipped instead of stopping every page that shows BBCode, and an
  [img] tag works with a language pack that lacks the image texts.
- Control panel: pages that choose another template after the header raise no
  warning, the user search popup keeps every selected group across pages, and
  a failed Profile registration step shows its errors.
- XML-RPC (off by default): /xmlrpc.php parses requests again, including
  indented ones, and accepts a name or password holding a quote or backslash.
- PHP: a NUL byte in request input no longer stops a page with a ValueError on
  PHP 8.6, and the XML parsers raise no deprecation notices on PHP 8.4 and
  later. PHP 8.6 is tested but not yet supported: the bundled HTMLPurifier
  allows PHP up to 8.5.
- Bundled libraries: XMF 1.3.4 RC3, xoops/helpers 1.1.3 and
  xoops/smartyextensions 2.0.0 Beta1 are updated; composer/semver 3.6.0 and
  xoops/moduletools 1.7.1 are new. See docs/changelog.270.txt for the full
  list.

Changes in Beta 1 (since 2.7.4)
-----------------------------------
- The media uploader accepts .webp images again, and reports only the current
  file's errors when a request names several upload fields.
- The theme selector block works again after a theme was chosen through a URL,
  and the admin redirect page renders in the theme it was built for.
- Editor forms stay within the content column across themes; SCEditor toolbars
  wrap, restored drafts open visually and image previews fit.
- The upgrade wizard no longer re-applies the 2.3.3-to-2.4.0 patch after 2.7.3,
  and its Smarty 4 template repair no longer quotes across two adjacent tags;
  it also restores templates damaged by earlier runs.
- Protector writes its sanitised request values back again, matches reliable
  IPs exactly or by subnet, counts every password login against the submitted
  user name, and never drops tables of the working prefix.
- Sign-in and sessions: an expired session is refused on every lookup, a
  remember-me login starts a fresh session ID, and LDAP and Active Directory
  sign-in refuses an empty password.
- Administrator permissions are tightened in the System and Profile user
  admin, the block admin and the module list; registration and profile edits
  can no longer write rank, post-count or credential columns.
- Text handling: automatic linking works only on text between tags, BBCode
  arguments cannot rewrite an earlier tag's attribute, and Markdown rendering
  bounds its work on untrusted text.
- image.php serves a picture with the type its content decodes as, limits the
  filters applied to an edited image and sends nosniff on every response.
- Uploads strip every inner executable extension, and the file cache refuses
  keys that contain a path.
- Bundled libraries: XMF 1.3.4 (RC1 in Beta 1, now RC3), which tightens input
  filtering, safe serialization, search highlighting and database identifier
  quoting, and symfony/yaml 7.4.23.

Upgrading from 2.7.4 or 2.7.5 Beta 1
-----------------------------------
Copy the new files over the web root and run the upgrade wizard. There are no
database changes since 2.7.4. The wizard's 2.7.5 patch updates
xoops_data/.htaccess for Apache 2.4: it rewrites the file only when it still
holds the two lines XOOPS shipped, and reports a file it cannot write.

- Theme and module templates: the xoops/smartyextensions 2.0.0 plugins
  sanitize_url, linkify, pretty_print_json, format_phone_number, format_date,
  relative_time, format_currency, number_format, mask_email and datetime_diff,
  the direct output of xo_get_config and get_session_data, and <{asset_url}>
  of xoops/helpers now HTML-escape their own output. Remove any |escape after
  them, or the output is double-encoded. linkify takes plain text and shows
  HTML passed to it as text.
- A module script that outputs CSS or JavaScript through the XOOPS bootstrap
  must send the matching Content-Type header, because every page now carries
  X-Content-Type-Options: nosniff. Static files are served by the web server:
  add the header there too (Apache: Header always set X-Content-Type-Options
  "nosniff").
- xoops/moduletools 1.7.1 is bundled, so a module can use it on any 2.7.5
  site; the separate mTools module is only needed on 2.7.0 to 2.7.4.
- If xoopsconfig.php sets 'xmlrpc_enabled' => true, /xmlrpc.php now answers
  calls; until this release it rejected every request. Leave the setting off
  if you do not use XML-RPC.

- Module developers: XMF 1.3.4 rejects some input that 1.3.3 accepted.
  Xmf\Module\Helper\Session no longer stores or returns objects and enums, so
  store scalars and arrays. Xmf\Security\Serializer rejects a stored object or
  enum unless its class is passed as allowed. HTML accepted through
  Xmf\Request with MASK_ALLOW_HTML keeps link and image addresses only when
  they are relative or use http, https or mailto, so data:, tel: and ftp:
  addresses are removed. Xmf\Database\Tables::executeQueue() needs
  executeQueue(true) to write from a GET request. Release notes:
  https://github.com/XOOPS/xmf/releases/tag/v1.3.4-RC3

- If your site used the Dark admin theme, it now loads Transition; switch dark
  mode on with the toggle in the header. The leftover
  modules/system/themes/dark folder is no longer offered in the theme selector
  and can be deleted.
- If you copied extras/login.php (the SSL popup login) somewhere on your site,
  replace that copy: updating the core does not patch it.
- A site running a custom template set should re-import it, as for any core
  template change.

Upgrading from 2.7.3 or earlier
-----------------------------------
Copy the new files over the web root and run the upgrade wizard. From 2.7.3 it
creates the user_2fa table and the two-factor preference, registers the
SCEditor emoticons as smileys, and adds the Editors preferences. No
mainfile.php changes are needed. The notes under "Upgrading from 2.7.4 or
2.7.5 Beta 1" apply to this upgrade as well; in particular, replace any copy of
extras/login.php,
because an older copy skips the two-factor step.

- Two-factor authentication needs the PHP sodium extension; the wizard reports
  whether it is available. Operations notes, including key backup and the
  locked-out administrator procedure, are in docs/2fa-operations.md.
- Every remembered device logs in once more after the upgrade: remember-me
  tokens now carry a fingerprint of the password, and older tokens have none.
- An upgraded site keeps its current editor settings; SCEditor becomes the
  default editor only on a fresh install. To get SCEditor's MP3 button, set
  'mp3' => 1 in xoops_data/configs/textsanitizer/config.php.

Debugbar module
-----------------------------------
The Debugbar module is not included in the XOOPS Core download. The
php-debugbar/php-debugbar library remains bundled in xoops_lib so the standalone
module works without a separate Composer installation.

Download the current Debugbar module release from:
https://github.com/XoopsModules27x/debugbar/releases/latest

Copy the included debugbar directory to htdocs/modules/debugbar, then install or
update it from System Admin -> Modules. Existing Debugbar users should replace
the module files and run Update; uninstalling first is not required.

Languages
-----------------------------------
XOOPS is available in 37 community translations, maintained at:
https://github.com/XoopsLanguages

See docs/TRANSLATIONS.md for the full list of languages and the current release
page for each language pack. Language packs are published independently, so
check each release page for its declared XOOPS compatibility.

XOOPS 2.7.5 adds ten English language constants: the "Dark mode" label of the
Default and Transition admin themes and, since Beta 1, four upgrade wizard
messages, one comment message and four texts of the Composer Packages panel in
the admin themes. See docs/lang_diff.txt for the exact definitions.

Help wanted: please help us find and fix translation errors, and help us add
and review more languages. Every correction makes XOOPS better worldwide.

How to contribute
-----------------------------------
Bug reports and feature requests: https://github.com/XOOPS/XoopsCore27/issues
Patch and enhancement: https://github.com/XOOPS/XoopsCore27/blob/master/CONTRIBUTING.md
Documentation: https://xoops.github.io/xoops-docs/
Support Forums: https://xoops.org/modules/newbb/

Thank you
-----------------------------------
Thank you to everyone who submitted pull requests, reported issues, tested the
release packages, translated strings, reviewed security findings, and kept the
conversation going on the forums and on GitHub throughout the 2.7.5 cycle.

* And a standing THANK-YOU to **[JetBrains](https://www.jetbrains.com/)** for the complimentary [PhpStorm](https://www.jetbrains.com/phpstorm/) licenses that power the core team's development.


XOOPS Development Team
November 2026
