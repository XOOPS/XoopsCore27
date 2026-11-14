<?php

/**
 * SystemFineImUploadHandler class to work with ajaxfineupload.php endpoint
 * to facilitate uploads for the system image manager
 *
 * Do not use or reference this directly from your client-side code.
 * Instead, this should be required via the endpoint.php or endpoint-cors.php
 * file(s).
 *
 * @license   MIT License (MIT)
 * @copyright Copyright (c) 2015-present, Widen Enterprises, Inc.
 * @link      https://github.com/FineUploader/php-traditional-server
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2015-present, Widen Enterprises, Inc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

class SystemFineAvatarUploadHandler extends SystemFineUploadHandler
{
    /**
     * XoopsFineAvatarUploadHandler constructor.
     * @param stdClass $claims claims passed in JWT header
     */
    public function __construct(\stdClass $claims)
    {
        parent::__construct($claims);
        $this->allowedMimeTypes = ['image/gif', 'image/jpeg', 'image/png'];
        $this->allowedExtensions = ['gif', 'jpeg', 'jpg', 'png'];
        $this->setByteLimit((int) ($this->limits()['avatar_maxsize'] ?? 0));
    }

    /**
     * The site's avatar limits from the user preferences.
     *
     * @return array<string, mixed> avatar_maxsize (bytes), avatar_width and avatar_height (pixels)
     */
    protected function limits(): array
    {
        /** @var XoopsConfigHandler $configHandler */
        $configHandler = xoops_getHandler('config');

        return $configHandler->getConfigsByCat(XOOPS_CONF_USER);
    }

    protected function storeUploadedFile($target, $mimeType, $uuid)
    {
        $limits     = $this->limits();
        $pixelError = $this->pixelError(
            (string) $_FILES[$this->inputName]['tmp_name'],
            (int) ($limits['avatar_width'] ?? 0),
            (int) ($limits['avatar_height'] ?? 0),
        );
        if (null !== $pixelError) {
            return ['error' => $pixelError, 'preventRetry' => true];
        }

        $pathParts = pathinfo((string) $this->getName());
        $avatarName = uniqid('savt', false) . '.' . strtolower($pathParts['extension']);
        $avatarNicename = str_replace(['_', '-'], ' ', $pathParts['filename']);
        $avatarPath = XOOPS_ROOT_PATH . '/uploads/avatars/' . $avatarName;

        if (false === move_uploaded_file($_FILES[$this->inputName]['tmp_name'], $avatarPath)) {
            return false;
        }
        /** @var  XoopsAvatarHandler $avt_handler */
        $avt_handler = xoops_getHandler('avatar');
        $avatar = $avt_handler->create();

        $avatar->setVar('avatar_file', 'avatars/' . $avatarName);
        $avatar->setVar('avatar_name', $avatarNicename);
        $avatar->setVar('avatar_mimetype', $mimeType);
        $avatar->setVar('avatar_created', time());
        $avatar->setVar('avatar_display', 1);
        $avatar->setVar('avatar_weight', 0);
        $avatar->setVar('avatar_type', 's');
        if (!$avt_handler->insert($avatar)) {
            return [
                'error' => sprintf(_FAILSAVEIMG, $avatar->getVar('avatar_name')),
            ];
        }
        return ['success' => true, 'uuid' => $uuid];
    }
}
