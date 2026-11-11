XOOPS 2.7.5 Beta 1

The XOOPS Development Team is pleased to announce XOOPS 2.7.5 Beta 1, the
first test release of XOOPS 2.7.5. This is a maintenance release: it collects
the bug fixes and security hardening made since XOOPS 2.7.4 and adds a
light/dark toggle to the Default and Transition admin themes. XOOPS 2.7.5 runs
on PHP 8.2 through 8.5.

This is a beta: more fixes may follow before the final release. Please test it
on a staging copy of your site, not on a production site, and report anything
you find.

Admin themes: Default and Transition have a light/dark toggle that remembers
your choice. Default adds dark styling for module panels, permission grids and
SCEditor. The standalone Dark theme is removed; a site that had selected it
loads Transition, where the header toggle switches dark mode on.

Download XOOPS 2.7.5 Beta 1 from GitHub: https://github.com/XOOPS/XoopsCore27/releases

For full documentation on installing or upgrading XOOPS please see:
https://xoops.github.io/xoops-docs/

Changes since 2.7.4
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
- Bundled libraries: XMF 1.3.4 RC1, which tightens input filtering, safe
  serialization, search highlighting and database identifier quoting, and
  symfony/yaml 7.4.23. See docs/changelog.270.txt for the full list.

Upgrading from 2.7.4
-----------------------------------
Copy the new files over the web root. There are no database changes since
2.7.4; running the upgrade wizard is harmless but not required.

- Module developers: XMF 1.3.4 rejects some input that 1.3.3 accepted.
  Xmf\Module\Helper\Session and Xmf\Security\Serializer no longer store or
  return objects and enums, so store scalars and arrays. HTML accepted through
  Xmf\Request with MASK_ALLOW_HTML keeps link and image addresses only when
  they are relative or use http, https or mailto, so data:, tel: and ftp:
  addresses are removed. Xmf\Database\Tables::executeQueue() needs
  executeQueue(true) to write from a GET request. Release notes:
  https://github.com/XOOPS/xmf/releases/tag/v1.3.4-RC1

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
mainfile.php changes are needed. The notes under "Upgrading from 2.7.4" apply
to this upgrade as well; in particular, replace any copy of extras/login.php,
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

XOOPS 2.7.5 adds one English language constant, the "Dark mode" label of the
Default and Transition admin themes. See docs/lang_diff.txt for the exact
definition.

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
