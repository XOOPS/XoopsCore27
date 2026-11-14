<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal\Presentation;

use Xoops\Helpers\Utility\HtmlBuilder;

/**
 * One rule for turning a stored object field into presentable text, shared by the admin
 * table, the single view and the CSV export so the three never drift apart.
 *
 * XoopsObject::getVar('s') HTML-escapes TXTBOX and runs TXTAREA through displayTarea(),
 * but returns URL, EMAIL, OTHER, FLOAT, DECIMAL, ENUM and the time types raw. Escaping the
 * 's' result again therefore double-encodes text fields, while not escaping it leaves the
 * other types injectable. Both variants below branch on the declared data type instead.
 *
 * @internal Compatibility implementation detail; not part of the ModuleTools public API.
 */
final class ObjectValuePresenter
{
    /** HTML for one field: core's sanitised output for text types, escaped raw value otherwise. */
    public static function html(\XoopsObject $object, string $key): string
    {
        if (!isset($object->vars[$key])) {
            return '';
        }
        if (self::isTextType($object, $key)) {
            return self::stringify($object->getVar($key, 's'));
        }

        return self::escape(self::stringify($object->getVar($key, 'n')));
    }

    /** Plain text for one field (CSV): the stored value, never HTML entities or rendered markup. */
    public static function plain(\XoopsObject $object, string $key): string
    {
        if (!isset($object->vars[$key])) {
            return '';
        }

        return self::stringify($object->getVar($key, 'n'));
    }

    /** Anything a value method or caller hands over, HTML-escaped once. */
    public static function escape(string $value): string
    {
        return HtmlBuilder::escape($value);
    }

    private static function isTextType(\XoopsObject $object, string $key): bool
    {
        $type = (int) ($object->vars[$key]['data_type'] ?? 0);

        return in_array($type, [XOBJ_DTYPE_TXTBOX, XOBJ_DTYPE_TXTAREA], true);
    }

    private static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(static fn ($item): string => is_scalar($item) ? (string) $item : '', $value));
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
