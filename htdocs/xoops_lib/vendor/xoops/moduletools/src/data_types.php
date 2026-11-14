<?php

declare(strict_types=1);

/**
 * Data types beyond the core XOBJ_DTYPE_* set, used by DynamicObject, ObjectFormBuilder
 * and ObjectController. Loaded through Composer's "files" autoload so a plain XoopsObject
 * can use them before any ModuleTools class is loaded, e.g. to declare an upload field:
 * `$this->vars['photo']['custom_type'] = XOBJ_DTYPE_IMAGE;`.
 *
 * Only types core never defines belong here: this file runs before kernel/object.php, and core
 * 2.7+ defines XOBJ_DTYPE_FLOAT (13) unguarded, so its fallback stays in DynamicObject.php.
 */

defined('XOBJ_DTYPE_SIMPLE_ARRAY') || define('XOBJ_DTYPE_SIMPLE_ARRAY', 101);
defined('XOBJ_DTYPE_CURRENCY') || define('XOBJ_DTYPE_CURRENCY', 200);
defined('XOBJ_DTYPE_TIME_ONLY') || define('XOBJ_DTYPE_TIME_ONLY', 202);
defined('XOBJ_DTYPE_URLLINK') || define('XOBJ_DTYPE_URLLINK', 203);
defined('XOBJ_DTYPE_FILE') || define('XOBJ_DTYPE_FILE', 204);
defined('XOBJ_DTYPE_IMAGE') || define('XOBJ_DTYPE_IMAGE', 205);
defined('XOBJ_DTYPE_FORM_SECTION') || define('XOBJ_DTYPE_FORM_SECTION', 210);
defined('XOBJ_DTYPE_FORM_SECTION_CLOSE') || define('XOBJ_DTYPE_FORM_SECTION_CLOSE', 211);
