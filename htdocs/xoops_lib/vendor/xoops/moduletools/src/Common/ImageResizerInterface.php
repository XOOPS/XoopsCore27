<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/**
 * Contract for a pure image resizer: a {@see ResizeRequest} in, a {@see ResizeResult} out.
 *
 * Implementations MUST be side-effect free with respect to the request lifecycle — no
 * `$_POST`/`$_FILES` reads, no echo, no redirect. They compute pixels and write the target
 * file; everything else (which upload field, where to save, what the admin chose) belongs
 * to the caller.
 *
 * @api Stable Common-tier API (Lab\* is experimental, module-local code is private).
 * @since 1.2.0
 */
interface ImageResizerInterface
{
    public function resize(ResizeRequest $request): ResizeResult;
}
