<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Admin;

use Xoops\Helpers\Utility\HtmlBuilder;
use Xoops\ModuleTools\Internal\Presentation\ObjectValuePresenter;

final class SingleView
{
    /** @var list<ObjectRow> */
    private array $rows = [];

    public function __construct(private readonly \XoopsObject $object, private readonly bool $userSide = false, private readonly array $actions = [], private readonly bool $headerAsRow = true)
    {
    }

    public function addRow(ObjectRow $row): void
    {
        $this->rows[] = $row;
    }

    public function fetch(bool $debug = false): string
    {
        return $this->render(true, $debug);
    }

    public function render(bool $fetchOnly = false, bool $debug = false): ?string
    {
        $escape = static fn (mixed $value): string => HtmlBuilder::escape((string) $value);
        $html = '<dl class="moduletools-single-view">';
        foreach ($this->rows as $row) {
            // hideFieldFromSingleView() wins over a registered row, before any value is read.
            if (false === ($this->object->vars[$row->key]['displayOnSingleView'] ?? true)) {
                continue;
            }
            $caption = $this->object->vars[$row->key]['form_caption'] ?? $row->key;
            $class = false !== $row->class ? ' class="' . $escape($row->class) . '"' : '';
            if ($row->header) {
                // A header row is a section title; with headerAsRow=false it is dropped entirely.
                if ($this->headerAsRow) {
                    $html .= '<dt' . ('' !== $class ? $class : ' class="header"') . '>' . $escape($caption) . '</dt>';
                }
                continue;
            }
            // Same rule as ObjectTable: a value method returns presentation HTML and is emitted as is;
            // a plain field goes through the shared presenter (see ObjectValuePresenter).
            $cell = false !== $row->valueMethod && method_exists($this->object, $row->valueMethod)
                ? (string) $this->object->{$row->valueMethod}()
                : ObjectValuePresenter::html($this->object, $row->key);
            $html .= '<dt' . $class . '>' . $escape($caption) . '</dt><dd' . $class . '>' . $cell . '</dd>';
        }
        $html .= '</dl>';
        // DynamicObject carries its handler; a plain XoopsObject may not, so read it dynamically.
        $handler = get_object_vars($this->object)['handler'] ?? null;
        if ([] !== $this->actions && is_object($handler) && is_string($handler->keyName ?? null)) {
            $key = (string) $handler->keyName;
            $id = (int) $this->object->getVar($key, 'n');
            $script = $escape(xoops_getenv('SCRIPT_NAME'));
            $links = [];
            foreach ($this->actions as $action) {
                $action = (string) $action;
                $operation = 'edit' === $action ? 'mod' : ('delete' === $action ? 'del' : $action);
                $links[] = '<a href="' . $script . '?op=' . rawurlencode($operation) . '&amp;' . rawurlencode($key) . '=' . $id . '">' . $escape(ucfirst($action)) . '</a>';
            }
            $html .= '<p class="moduletools-single-view-actions">' . implode(' ', $links) . '</p>';
        }
        if ($fetchOnly) {
            return $html;
        }
        echo $html;
        return null;
    }
}
