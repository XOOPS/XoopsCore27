<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Admin;

use Xoops\Helpers\Utility\HtmlBuilder;
use Xoops\ModuleTools\Common\Output;
use Xoops\ModuleTools\Internal\Presentation\ObjectValuePresenter;

/**
 * Transitional table presenter for modules moving from SmartObject.
 * New screens should expose the same rows through XMF DataTable builders.
 */
class ObjectTable
{
    /** @var list<ObjectColumn> */
    protected array $columns = [];
    /** @var list<string> */
    protected array $customActions = [];
    /** @var list<string> */
    protected array $actions;
    protected array $introButtons = [];
    /** @var array<string, array{method: string, default: mixed}> */
    protected array $filters = [];
    /** @var list<string> */
    protected array $withSelected = [];
    protected string $tableId = 'moduletools-object-table';
    protected bool $showTools = true;
    protected ?array $objects = null;

    public function __construct(
        protected readonly \XoopsPersistableObjectHandler $handler,
        protected ?\CriteriaCompo $criteria = null,
        array $actions = ['edit', 'delete'],
        protected bool $userSide = false,
    ) {
        $this->actions = array_values(array_map(strval(...), $actions));
    }

    public function addColumn(ObjectColumn $column): void
    {
        $this->columns[] = $column;
    }
    public function addCustomAction(string $method): void
    {
        $this->customActions[] = $method;
    }
    public function addIntroButton(string $name, string $location, string $value): void
    {
        $this->introButtons[] = compact('name', 'location', 'value');
    }
    public function addActionButton(string $operation, string|false $caption = false, string|false $text = false): void
    {
        $this->actions[] = $operation;
    }
    /**
     * Bulk actions: renders a checkbox per row and a "with selected" form that POSTs
     * `op=<action>`, `<keyName>[]=<id>` and the XOOPS token to the current script.
     *
     * @param list<string> $actions operation names the consumer's page handles
     */
    public function addWithSelectedActions(array $actions = []): void
    {
        $this->withSelected = array_values(array_map(strval(...), $actions));
    }
    public function addFilter(string $key, string $method, mixed $default = false): void
    {
        $this->filters[$key] = compact('method', 'default');
    }
    public function setDefaultFilter(mixed $filter): void
    {
    }
    public function setDefaultFilter2(mixed $filter): void
    {
    }
    public function setTableId(string $id): void
    {
        $this->tableId = $id;
    }
    public function setObjects(array $objects): void
    {
        $this->objects = $objects;
    }
    public function setDefaultSort(string $sort): void
    {
        $this->criteria ??= new \CriteriaCompo();
        $this->criteria->setSort($sort);
    }
    public function setDefaultOrder(string $order): void
    {
        $this->criteria ??= new \CriteriaCompo();
        $this->criteria->setOrder('DESC' === strtoupper($order) ? 'DESC' : 'ASC');
    }
    public function hideFilterAndLimit(): void
    {
        $this->showTools = false;
    }
    public function disableColumnsSorting(): void
    {
    }
    public function isForUserSide(): void
    {
        $this->userSide = true;
    }

    public function fetch(bool $debug = false): string
    {
        return $this->buildHtml();
    }

    public function render(bool $fetchOnly = false, bool $debug = false): ?string
    {
        $html = $this->buildHtml();
        if ($fetchOnly) {
            return $html;
        }
        echo $html;
        return null;
    }

    /** @return list<\XoopsObject> */
    protected function fetchObjects(): array
    {
        if (null !== $this->objects) {
            return array_values(array_filter($this->objects, static fn ($object): bool => $object instanceof \XoopsObject));
        }
        return array_values($this->handler->getObjects($this->filteredCriteria()));
    }

    /** The table criteria plus one equality clause per filter that carries a selected value. */
    protected function filteredCriteria(): ?\CriteriaCompo
    {
        if ([] === $this->filters) {
            return $this->criteria;
        }
        $criteria = new \CriteriaCompo();
        if (null !== $this->criteria) {
            $criteria->add($this->criteria);
            $criteria->setSort($this->criteria->getSort());
            $criteria->setOrder($this->criteria->getOrder());
            $criteria->setLimit($this->criteria->getLimit());
            $criteria->setStart($this->criteria->getStart());
            $criteria->setGroupBy($this->criteria->getGroupby());
        }
        foreach ($this->filters as $key => $filter) {
            $value = $this->filterValue($key, $filter['default']);
            // Only a value the handler itself offered may reach the criteria: Criteria::render()
            // quotes but does not escape, and filter_<key> is request input on user-side tables.
            if ('' === $value || !\array_key_exists($value, $this->filterOptions($filter))) {
                continue;
            }
            $criteria->add(new \Criteria($key, $this->escapeForCriteria($value)));
        }

        return $criteria;
    }

    /**
     * Selected filter value: the request's `filter_<key>` when the parameter is present
     * (an explicit '' means "All"), otherwise the configured default; '' applies no clause.
     */
    protected function filterValue(string $key, mixed $default): string
    {
        if (\Xmf\Request::hasVar('filter_' . $key, 'GET')) {
            return \Xmf\Request::getString('filter_' . $key, '', 'GET');
        }

        return (false !== $default && null !== $default) ? (string) $default : '';
    }

    /** Criteria values are quoted verbatim by core, so escape here; the handler's db is the source of truth. */
    private function escapeForCriteria(string $value): string
    {
        $db = $this->handler->db;

        return \is_object($db) && \method_exists($db, 'escape') ? (string) $db->escape($value) : \addslashes($value);
    }

    /**
     * @param array{method: string, default: mixed} $filter
     * @return array<string|int, string> option value => label, from the handler method named in addFilter()
     */
    protected function filterOptions(array $filter): array
    {
        $method = $filter['method'];
        if (!method_exists($this->handler, $method)) {
            return [];
        }
        $options = $this->handler->{$method}();

        return is_array($options) ? array_map(static fn ($label): string => (string) $label, $options) : [];
    }

    protected function buildHtml(): string
    {
        $escape = static fn (mixed $value): string => HtmlBuilder::escape((string) $value);
        $html = '';
        foreach ($this->introButtons as $button) {
            $html .= '<p><a class="btn btn-primary" href="' . $escape(Output::navigationTarget($button['location'])) . '">' . $escape($button['value']) . '</a></p>';
        }
        $script = $escape(xoops_getenv('SCRIPT_NAME'));
        if ([] !== $this->filters && $this->showTools) {
            $html .= '<form method="get" action="' . $script . '" class="moduletools-filters">';
            foreach ($this->filters as $key => $filter) {
                $selected = $this->filterValue($key, $filter['default']);
                $caption = $this->handler->create()->vars[$key]['form_caption'] ?? $key;
                $html .= '<label>' . $escape($caption) . ' <select name="filter_' . $escape($key) . '" onchange="this.form.submit()">';
                $html .= '<option value="">' . $escape(defined('_ALL') ? _ALL : 'All') . '</option>';
                foreach ($this->filterOptions($filter) as $value => $label) {
                    $html .= '<option value="' . $escape($value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . $escape($label) . '</option>';
                }
                $html .= '</select></label> ';
            }
            $html .= '<noscript><button type="submit">' . $escape(defined('_SUBMIT') ? _SUBMIT : 'Submit') . '</button></noscript></form>';
        }
        $bulk = [] !== $this->withSelected;
        if ($bulk) {
            $html .= '<form method="post" action="' . $script . '" class="moduletools-with-selected">';
        }
        $html .= '<table id="' . $escape($this->tableId) . '" class="outer table table-striped"><thead><tr>';
        if ($bulk) {
            $html .= '<th class="center"></th>';
        }
        foreach ($this->columns as $column) {
            $caption = false !== $column->caption ? $column->caption : ($this->handler->create()->vars[$column->key]['form_caption'] ?? $column->key);
            $html .= '<th class="' . $escape($column->align) . '">' . $escape($caption) . '</th>';
        }
        if ([] !== $this->actions || [] !== $this->customActions) {
            $html .= '<th class="center">' . $escape(defined('_ACTION') ? _ACTION : 'Action') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        $key = (string) $this->handler->keyName;
        foreach ($this->fetchObjects() as $index => $object) {
            $html .= '<tr class="' . (0 === $index % 2 ? 'even' : 'odd') . '">';
            if ($bulk) {
                $html .= '<td class="center"><input type="checkbox" name="' . $escape($key) . '[]" value="' . (int) $object->getVar($key, 'n') . '"></td>';
            }
            foreach ($this->columns as $column) {
                $method = $column->valueMethod;
                $isCustom = false !== $method && method_exists($object, $method);
                // A value method returns presentation HTML (SmartObject contract) and is emitted as is;
                // a plain field goes through the shared presenter (see ObjectValuePresenter).
                $cell = $isCustom
                    ? (string) $object->{$method}(...(false === $column->parameters ? [] : $column->parameters))
                    : ObjectValuePresenter::html($object, $column->key);
                $html .= '<td class="' . $escape($column->align) . '">' . $cell . '</td>';
            }
            if ([] !== $this->actions || [] !== $this->customActions) {
                $id = (int) $object->getVar($key, 'n');
                $script = $escape(xoops_getenv('SCRIPT_NAME'));
                $links = [];
                foreach ($this->actions as $action) {
                    $operation = 'edit' === $action ? 'mod' : ('delete' === $action ? 'del' : $action);
                    $links[] = '<a href="' . $script . '?op=' . rawurlencode($operation) . '&amp;' . rawurlencode($key) . '=' . $id . '">' . $escape(ucfirst($action)) . '</a>';
                }
                foreach ($this->customActions as $method) {
                    if (method_exists($object, $method)) {
                        $links[] = (string) $object->{$method}();
                    }
                }
                $html .= '<td class="center">' . implode(' ', $links) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        if ($bulk) {
            $security = self::runtimeSecurity();
            $html .= '<p><select name="op">';
            foreach ($this->withSelected as $action) {
                $html .= '<option value="' . $escape($action) . '">' . $escape(ucfirst($action)) . '</option>';
            }
            $html .= '</select> <button type="submit">' . $escape(defined('_SUBMIT') ? _SUBMIT : 'Submit') . '</button>'
                . ((is_object($security) && method_exists($security, 'getTokenHTML')) ? $security->getTokenHTML() : '')
                . '</p></form>';
        }
        return $html;
    }

    /** @legacy-global-accessor */
    private static function runtimeSecurity(): ?object
    {
        $security = $GLOBALS['xoopsSecurity'] ?? null;

        return is_object($security) ? $security : null;
    }
}
