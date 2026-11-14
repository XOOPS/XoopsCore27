<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Object;

use Xoops\ModuleTools\Form\ObjectFormBuilder;
use Xoops\ModuleTools\Permission\ItemPermission;

// The extra XOBJ_DTYPE_* types are defined in src/data_types.php (Composer "files" autoload).
// FLOAT is core's (13, kernel/object.php, 2.7+); 201 only for an older core, after it has loaded.
defined('XOBJ_DTYPE_FLOAT') || define('XOBJ_DTYPE_FLOAT', 201);

/**
 * XOOPS object with declarative form metadata.
 *
 * This is the narrow, Core-owned successor for the useful data-model portion
 * of SmartObject. Persistence remains in XoopsPersistableObjectHandler and
 * rendering is delegated to ObjectFormBuilder.
 */
class DynamicObject extends \XoopsObject
{
    public ?object $handler = null;
    /** @var array<string, array<string, mixed>|string|false> */
    public array $controls = [];
    public string $_image_path = '';
    public string $_image_url = '';
    public bool $seoEnabled = false;
    public string $titleField = '';
    public string|false $summaryField = false;

    public function initVar($key, $dataType, $value = null, $required = false, $maxlength = null, $options = '', $enumerations = '', $formCaption = '', $formDescription = '', $sortBy = false, $persistent = true, $displayOnForm = true, $multilingual = false): void
    {
        $coreType = match ((int) $dataType) {
            // Core knows FLOAT (13) and casts it in cleanVars(); CURRENCY rides on it. Mapping
            // FLOAT to OTHER would drop that cast when core's constant is the one defined.
            XOBJ_DTYPE_CURRENCY => XOBJ_DTYPE_FLOAT,
            XOBJ_DTYPE_SIMPLE_ARRAY => XOBJ_DTYPE_ARRAY,
            XOBJ_DTYPE_URLLINK, XOBJ_DTYPE_FILE, XOBJ_DTYPE_IMAGE => XOBJ_DTYPE_TXTBOX,
            XOBJ_DTYPE_FORM_SECTION, XOBJ_DTYPE_FORM_SECTION_CLOSE => XOBJ_DTYPE_OTHER,
            default => $dataType,
        };
        parent::initVar($key, $coreType, $value, $required, $maxlength, $options);
        $this->vars[$key] += [
            'custom_type' => (int) $dataType,
            'enumerations' => $enumerations,
            'form_caption' => $formCaption ?: $key,
            'form_dsc' => $formDescription,
            'sortby' => (bool) $sortBy,
            'persistent' => (bool) $persistent,
            'displayOnForm' => (bool) $displayOnForm,
            'multilingual' => (bool) $multilingual,
            'readonly' => false,
        ];
    }

    public function initCommonVar(string $name, bool $displayOnForm = true, mixed $default = 'notdefined'): void
    {
        $value = 'notdefined' === $default ? ('counter' === $name || 'weight' === $name ? 0 : 1) : $default;
        $this->initVar($name, XOBJ_DTYPE_INT, $value, false, null, '', '', $name, '', 'weight' === $name, true, $displayOnForm);
        if (!in_array($name, ['counter', 'weight'], true)) {
            $this->setControl($name, 'yesno');
        }
    }

    public function initNonPersistableVar(string $name, int $dataType, mixed $value = null, string $caption = '', string $description = ''): void
    {
        $this->initVar($name, $dataType, $value, false, null, '', '', $caption, $description, false, false);
    }

    public function addFormSection(string $name, mixed $caption = false, bool $hide = false): void
    {
        $this->initVar($name, XOBJ_DTYPE_FORM_SECTION, $caption, false, null, '', '', (string) $caption, '', false, false, !$hide);
    }

    public function closeSection(string $name): void
    {
        $this->initVar('close_section_' . $name, XOBJ_DTYPE_FORM_SECTION_CLOSE, '', false, null, '', '', '', '', false, false);
    }

    public function setControl(string $name, array|string|false $control = []): void
    {
        $this->controls[$name] = $control;
    }

    public function getControl(string $name): array|string|false|null
    {
        return $this->controls[$name] ?? null;
    }

    public function setVarInfo(string $name, string $key, mixed $value): void
    {
        if (isset($this->vars[$name])) {
            $this->vars[$name][$key] = $value;
        }
    }

    public function getVarInfo(string $name = '', string $key = ''): mixed
    {
        if ('' === $name) {
            return $this->vars;
        }
        if ('' === $key) {
            return $this->vars[$name] ?? null;
        }

        return $this->vars[$name][$key] ?? null;
    }

    public function setImageDir(string $url, string $path): void
    {
        $this->_image_url = rtrim($url, '/') . '/';
        $this->_image_path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function getUploadDir(bool $path = false): string
    {
        return $this->getImageDir($path);
    }

    public function getImageDir(bool $path = false): string
    {
        return $path ? $this->_image_path : $this->_image_url;
    }

    public function id(): int
    {
        $key = $this->handler->keyName ?? array_key_first($this->vars);

        return (int) $this->getVar((string) $key, 'e');
    }

    public function title($format = 's'): string
    {
        $field = $this->titleField ?: (string) ($this->handler->identifierName ?? 'title');

        return (string) $this->getVar($field, $format);
    }

    public function summary(): string
    {
        return false === $this->summaryField ? '' : (string) $this->getVar($this->summaryField, 's');
    }

    public function getValueFor(string $name, bool $editor = true): mixed
    {
        if (!isset($this->vars[$name])) {
            return null;
        }

        $type = (int) ($this->vars[$name]['custom_type'] ?? $this->vars[$name]['data_type']);
        if (XOBJ_DTYPE_TXTAREA !== $type) {
            return $this->getVar($name, 'e');
        }

        // displayTarea() expects the stored text; 'e' is already HTML-escaped and would render literally.
        $myts = \MyTextSanitizer::getInstance();
        return $myts->displayTarea(
            (string) $this->getVar($name, 'n'),
            (bool) ($this->getVar('dohtml', 'n') ?? false),
            (bool) ($this->getVar('dosmiley', 'n') ?? true),
            (bool) ($this->getVar('doxcode', 'n') ?? true),
            (bool) ($this->getVar('doimage', 'n') ?? true),
            (bool) ($this->getVar('dobr', 'n') ?? true),
        );
    }

    public function accessGranted(string $name): bool
    {
        if (null === $this->handler || !method_exists($this->handler, 'getModuleInfo')) {
            return false;
        }
        $module = $this->handler->getModuleInfo();
        if (!is_object($module)) {
            return false;
        }
        $user = self::runtimeUser();
        $groups = is_object($user) ? $user->getGroups() : [XOOPS_GROUP_ANONYMOUS];

        return ItemPermission::forModule($module)->isGranted($name, $this->id(), array_map(intval(...), $groups));
    }

    public function getForm(string $caption = '', string $operation = 'save', string|false $action = false, string|false $submitCaption = false, string|false $cancelAction = false, bool $captcha = false): \XoopsThemeForm
    {
        return ObjectFormBuilder::build($this, $caption, $operation, $action, $submitCaption, $cancelAction, $captcha);
    }

    public function setErrors($error, $prefix = false): void
    {
        $prefix = false !== $prefix ? (string) $prefix : '';
        // XoopsMediaUploader::getErrors(false) hands over a list; keep every message instead of "Array".
        parent::setErrors(
            \is_array($error)
                ? \array_map(static fn ($message): string => $prefix . (string) $message, \array_values($error))
                : $prefix . (string) $error
        );
    }

    public function hasError(): bool
    {
        return [] !== $this->getErrors();
    }

    public function hideFieldFromForm(string $name): void
    {
        $this->setVarInfo($name, 'displayOnForm', false);
    }

    public function showFieldOnForm(string $name): void
    {
        $this->setVarInfo($name, 'displayOnForm', true);
    }

    public function hideFieldFromSingleView(string $name): void
    {
        $this->setVarInfo($name, 'displayOnSingleView', false);
    }

    public function displayFieldOnSingleView(string $name): void
    {
        $this->setVarInfo($name, 'displayOnSingleView', true);
    }

    public function makeFieldReadOnly(string $name): void
    {
        $this->setVarInfo($name, 'readonly', true);
    }

    public function setFieldForSorting(string $name): void
    {
        $this->setVarInfo($name, 'sortby', true);
    }

    public function setFieldAsRequired(string $name, bool $required = true): void
    {
        $this->setVarInfo($name, 'required', $required);
    }

    /** @legacy-global-accessor */
    private static function runtimeUser(): mixed
    {
        return $GLOBALS['xoopsUser'] ?? null;
    }
}
