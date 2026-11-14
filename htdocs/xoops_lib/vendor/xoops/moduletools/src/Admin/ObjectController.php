<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Admin;

use Xmf\Request;
use Xoops\ModuleTools\Common\Output;
use Xoops\ModuleTools\Object\DynamicObject;
use Xoops\ModuleTools\Permission\ItemPermission;

/** Standard POST-to-XoopsObject CRUD coordinator for module admin pages. */
final readonly class ObjectController
{
    public function __construct(public \XoopsPersistableObjectHandler $handler)
    {
    }

    public function postDataToObject(\XoopsObject $object): void
    {
        foreach ($object->vars as $name => $metadata) {
            if (!self::isWritableField($metadata) || !array_key_exists($name, $_POST)) {
                continue;
            }
            $value = $_POST[$name];
            $dataType = (int) $metadata['data_type'];
            // An array posted for a scalar field ("title[]=x") would reach core's cleanVars()
            // as strlen(array); only ARRAY fields and the date/time structure accept one.
            if (is_array($value) && !in_array($dataType, [XOBJ_DTYPE_ARRAY, XOBJ_DTYPE_STIME, XOBJ_DTYPE_MTIME, XOBJ_DTYPE_LTIME], true)) {
                continue;
            }
            if (in_array($dataType, [XOBJ_DTYPE_STIME, XOBJ_DTYPE_MTIME, XOBJ_DTYPE_LTIME], true)) {
                if (is_array($value) && isset($value['date'])) {
                    $value = strtotime((string) $value['date']) + (int) ($value['time'] ?? 0);
                } elseif (!is_numeric($value)) {
                    $value = strtotime((string) $value) ?: 0;
                }
            }
            $object->setVar((string) $name, $value);
        }
    }

    /**
     * @param callable(\XoopsObject): bool|null $authorize Decides whether the current visitor may store
     *                                                    this object (ownership, permission …). On the user
     *                                                    side ($userSide) a missing callback falls back to
     *                                                    DynamicObject::accessGranted('add'|'edit') and
     *                                                    otherwise refuses: the XOOPS token alone only
     *                                                    proves the form was ours, not that the id is theirs.
     * @param bool $userSide true on public (non-admin) pages: authorization becomes mandatory and the
     *                       handler's item permissions are never rewritten from the POST body.
     */
    public function storeFromDefaultForm(string $createdMessage, string $modifiedMessage, string|false|null $redirectPage = false, bool $debug = false, bool $extended = false, ?callable $authorize = null, bool $userSide = false): \XoopsObject|false
    {
        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
            return false;
        }
        self::assertValidToken();

        $key = (string) $this->handler->keyName;
        $id = Request::getInt($key, 0, 'POST');
        $object = $id > 0 ? $this->handler->get($id) : $this->handler->create();
        if (!$object instanceof \XoopsObject || ($id > 0 && $object->isNew())) {
            return false;
        }
        if (!self::mayStore($object, $userSide, $authorize)) {
            return false;
        }
        $isNew = $object->isNew();
        $this->postDataToObject($object);
        $this->receiveUploads($object);
        // A rejected upload records errors on the object; never persist or redirect past it.
        if ([] !== $object->getErrors() || !$this->handler->insert($object)) {
            return false;
        }

        // Item permissions are an admin decision: a public form must not be able to grant itself access.
        if (!$userSide && method_exists($this->handler, 'getPermissions') && method_exists($this->handler, 'getModuleInfo')) {
            $module = $this->handler->getModuleInfo();
            if (is_object($module)) {
                $permissions = ItemPermission::forModule($module);
                foreach ((array) $this->handler->getPermissions() as $definition) {
                    $name = (string) ($definition['perm_name'] ?? '');
                    if ('' === $name) {
                        continue;
                    }
                    // replace() restores the previous grants on failure; the object stays saved,
                    // so report the failure instead of redirecting with a success message.
                    if (!$permissions->replace($name, (int) $object->getVar($key, 'n'), array_map(intval(...), (array) ($_POST[$name] ?? [])))) {
                        return false;
                    }
                }
            }
        }

        if (null !== $redirectPage) {
            $target = false === $redirectPage ? (string) ($_SERVER['HTTP_REFERER'] ?? '') : $redirectPage;
            redirect_header(self::onSiteTarget($target), 2, $isNew ? $createdMessage : $modifiedMessage);
            return $object;
        }

        return $object;
    }

    /** @param callable(\XoopsObject): bool|null $authorize see storeFromDefaultForm() */
    public function storeSmartObject(bool $debug = false, bool $extended = false, ?callable $authorize = null, bool $userSide = false): \XoopsObject|false
    {
        return $this->storeFromDefaultForm('', '', null, $debug, $extended, $authorize, $userSide);
    }

    /** @param callable(\XoopsObject): bool|null $authorize */
    private static function mayStore(\XoopsObject $object, bool $userSide, ?callable $authorize): bool
    {
        if (null !== $authorize) {
            return true === $authorize($object);
        }
        if (!$userSide) {
            return true; // admin pages sit behind cp_header(); the token check is the guard
        }

        // A new object has id 0, so 'add' is checked against item 0: the module-wide create right.
        // No grant on item 0 means refused; a POSTed existing id loads that object and checks 'edit'.
        return $object instanceof DynamicObject && $object->accessGranted($object->isNew() ? 'add' : 'edit');
    }

    public function storeSmartObjectD(): \XoopsObject|false
    {
        return $this->storeSmartObject(true);
    }

    /**
     * @param callable(\XoopsObject): bool|null $authorize Decides whether the current visitor may delete this
     *                                                    object. On the user side ($userSide) a missing
     *                                                    callback falls back to DynamicObject::accessGranted()
     *                                                    for the operation and otherwise refuses: the token
     *                                                    proves the form was ours, not that the id is theirs.
     */
    public function handleObjectDeletion(string|false $confirmMessage = false, string $operation = 'del', bool $userSide = false, ?callable $authorize = null): void
    {
        $key = (string) $this->handler->keyName;
        $id = 'POST' === ($_SERVER['REQUEST_METHOD'] ?? 'GET') ? Request::getInt($key, 0, 'POST') : Request::getInt($key, 0, 'GET');
        $object = $id > 0 ? $this->handler->get($id) : false;
        if (!$object instanceof \XoopsObject || $object->isNew()) {
            redirect_header(self::onSiteTarget((string) ($_SERVER['HTTP_REFERER'] ?? '')), 3, defined('_NOPERM') ? _NOPERM : 'Record not found.');
            return;
        }
        if (!self::mayDelete($object, $operation, $userSide, $authorize)) {
            redirect_header(self::onSiteTarget((string) ($_SERVER['HTTP_REFERER'] ?? '')), 3, defined('_NOPERM') ? _NOPERM : 'Permission denied.');
            return;
        }
        if ('POST' === ($_SERVER['REQUEST_METHOD'] ?? 'GET') && Request::getInt('confirm', 0, 'POST')) {
            self::assertValidToken();
            $redirect = self::onSiteTarget(Request::getString('redirect_page', '', 'POST'));
            $ok = $this->handler->delete($object);
            redirect_header($redirect, 2, $ok ? (defined('_DELETEDSUCCESS') ? _DELETEDSUCCESS : 'Deleted.') : (defined('_ERRORS') ? _ERRORS : 'Delete failed.'));
            return;
        }

        $message = false === $confirmMessage ? (defined('_DELETE') ? _DELETE : 'Delete this record?') : $confirmMessage;
        xoops_confirm(
            ['op' => $operation, $key => $id, 'confirm' => 1, 'redirect_page' => (string) ($_SERVER['HTTP_REFERER'] ?? xoops_getenv('SCRIPT_NAME'))],
            xoops_getenv('SCRIPT_NAME'),
            sprintf($message, (string) $object->getVar((string) $this->handler->identifierName, 's')),
            defined('_DELETE') ? _DELETE : 'Delete',
        );
    }

    /** @param callable(\XoopsObject): bool|null $authorize see handleObjectDeletion() */
    public function handleObjectDeletionFromUserSide(string|false $confirmMessage = false, string $operation = 'del', ?callable $authorize = null): void
    {
        $this->handleObjectDeletion($confirmMessage, $operation, true, $authorize);
    }

    /** @param callable(\XoopsObject): bool|null $authorize */
    private static function mayDelete(\XoopsObject $object, string $operation, bool $userSide, ?callable $authorize): bool
    {
        if (null !== $authorize) {
            return true === $authorize($object);
        }
        if (!$userSide) {
            return true; // admin pages sit behind cp_header(); the token check is the guard
        }

        return $object instanceof DynamicObject && $object->accessGranted($operation);
    }

    /**
     * A redirect target that stays on this site: a local path, or an absolute URL under XOOPS_URL
     * (the referer is absolute by nature). Anything else falls back to the current script.
     */
    private static function onSiteTarget(string $target): string
    {
        $fallback = (string) xoops_getenv('SCRIPT_NAME');
        if (defined('XOOPS_URL') && '' !== $target) {
            $site = rtrim((string) XOOPS_URL, '/') . '/';
            if (0 === strncasecmp($target, $site, strlen($site)) && 1 !== preg_match('/[\x00-\x1F\x7F]|\\\\/', $target)) {
                return $target;
            }
        }

        return Output::localPath($target, $fallback);
    }

    private function receiveUploads(\XoopsObject $object): void
    {
        if (!method_exists($this->handler, 'getImagePath')) {
            return;
        }
        // Every upload is checked before any file is moved: a refused field stops the save,
        // and a file already moved for another field would be left behind (or overwrite one).
        $accepted = [];
        foreach ($_FILES as $field => $file) {
            $name = str_starts_with((string) $field, 'upload_') ? substr((string) $field, 7) : (string) $field;
            if (!isset($object->vars[$name], $file['name']) || '' === (string) $file['name']) {
                continue;
            }
            // Refused loudly: the errors stop the save instead of dropping the selected file.
            // "photo" and "upload_photo" name one field: two files for it are refused, not raced.
            if (isset($accepted[$name]) || !self::isWritableField($object->vars[$name]) || !self::isUploadField($object, $name)) {
                $object->setErrors(sprintf('%s: this field does not accept uploads.', $name));
                continue;
            }
            $uploader = new \XoopsMediaUploader(
                $this->handler->getImagePath(true),
                (array) ($this->handler->_allowedMimeTypes ?? []),
                (int) ($this->handler->_maxFileSize ?? 1_000_000),
                (int) ($this->handler->_maxWidth ?? 0),
                (int) ($this->handler->_maxHeight ?? 0),
            );
            if ($uploader->fetchMedia((string) $field) && self::passesUploadChecks($uploader)) {
                $accepted[$name] = $uploader;
            } else {
                $object->setErrors($uploader->getErrors(false));
            }
        }
        if ([] !== $object->getErrors()) {
            return;
        }
        foreach ($accepted as $name => $uploader) {
            if (!$uploader->upload()) {
                $object->setErrors($uploader->getErrors(false));

                return;
            }
            $object->setVar($name, $uploader->getSavedFileName());
        }
    }

    /**
     * The size, dimension and type checks core's upload() runs just before it moves the file,
     * run up front so one rejected file cannot follow another that was already moved.
     */
    private static function passesUploadChecks(\XoopsMediaUploader $uploader): bool
    {
        return array_all(
            ['checkMaxFileSize', 'checkMaxWidth', 'checkMaxHeight', 'checkMimeType', 'checkImageType'],
            static fn (string $check): bool => !method_exists($uploader, $check) || $uploader->{$check}(),
        ) && self::imageMatchesExtension($uploader);
    }

    /**
     * Core's _copyFile() compares an image's content with its saved extension only after the
     * move (then deletes it, possibly after overwriting a same-named file); the same test on the
     * temporary file refuses it first.
     */
    private static function imageMatchesExtension(\XoopsMediaUploader $uploader): bool
    {
        $name = (string) ($uploader->targetFileName ?? $uploader->mediaName ?? '');
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $imageExtensions = (array) ($uploader->imageExtensions ?? []);
        if (!in_array($ext, $imageExtensions, true)) {
            return true;
        }
        $tmp = (string) $uploader->mediaTmpName;
        $info = '' === $tmp ? false : @getimagesize($tmp);
        if (false !== $info && ($imageExtensions[(int) $info[2]] ?? null) === $ext) {
            return true;
        }
        $uploader->setErrors(defined('_ER_UP_SUSPICIOUSREFUSED') ? _ER_UP_SUSPICIOUSREFUSED : 'Suspicious image upload refused');

        return false;
    }

    /**
     * A field the form never shows (hideFieldFromForm) or marks read-only (makeFieldReadOnly)
     * is not writable from the request either, whatever the POST body or upload carries.
     *
     * @param array<string, mixed> $metadata
     */
    private static function isWritableField(array $metadata): bool
    {
        return false !== ($metadata['persistent'] ?? true)
            && true !== ($metadata['readonly'] ?? false)
            && false !== ($metadata['displayOnForm'] ?? true);
    }

    /**
     * The same rule ObjectFormBuilder uses to render a file input: a FILE/IMAGE type or a file control.
     * A plain XoopsObject declares an upload field with
     * `$this->vars['photo']['custom_type'] = XOBJ_DTYPE_IMAGE;` (or XOBJ_DTYPE_FILE) after initVar().
     */
    private static function isUploadField(\XoopsObject $object, string $name): bool
    {
        $type = (int) ($object->vars[$name]['custom_type'] ?? $object->vars[$name]['data_type'] ?? 0);
        if (in_array($type, [XOBJ_DTYPE_FILE, XOBJ_DTYPE_IMAGE], true)) {
            return true;
        }
        $control = method_exists($object, 'getControl') ? $object->getControl($name) : null;
        $controlName = is_array($control) ? (string) ($control['name'] ?? '') : (string) $control;

        return in_array($controlName, ['file', 'image', 'richfile'], true);
    }

    /** @legacy-global-accessor */
    private static function runtimeSecurity(): mixed
    {
        return $GLOBALS['xoopsSecurity'] ?? null;
    }

    /** Fail closed: without a security service no token can be checked, so no write happens. */
    private static function assertValidToken(): void
    {
        $security = self::runtimeSecurity();
        if (!is_object($security) || !method_exists($security, 'check') || !$security->check()) {
            throw new \RuntimeException(defined('_NOPERM') ? _NOPERM : 'Invalid security token.');
        }
    }
}
