<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Object;

class SeoObject extends DynamicObject
{
    public function initSeoVars(): void
    {
        $this->seoEnabled = true;
        $this->initVar('meta_keywords', XOBJ_DTYPE_TXTAREA, '', false);
        $this->initVar('meta_description', XOBJ_DTYPE_TXTAREA, '', false);
        $this->initVar('short_url', XOBJ_DTYPE_TXTBOX, '', false, 255);
    }

    public function meta_keywords($format = 's'): string
    {
        return (string) $this->getVar('meta_keywords', $format);
    }

    public function meta_description($format = 's'): string
    {
        return (string) $this->getVar('meta_description', $format);
    }
}
