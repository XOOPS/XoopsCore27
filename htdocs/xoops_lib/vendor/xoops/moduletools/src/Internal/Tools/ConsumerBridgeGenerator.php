<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Internal\Tools;

/**
 * Generates opt-in XOOPS 4 bridge surfaces from a module's install schema.
 *
 * This is build tooling, not a ModuleTools runtime API. It intentionally
 * refuses composite and non-integer identities because XMF 2's current
 * HandlerToRepositoryBridge contract accepts one integer identity only.
 *
 * @internal
 */
final class ConsumerBridgeGenerator
{
    /**
     * @return array<string, array{
     *     name:string,
     *     columns:array<string, array{sqlType:string,phpType:string,nullable:bool,autoIncrement:bool,dtype:string}>,
     *     primaryKey:list<string>,
     *     skipReason:?string
     * }>
     */
    public function parseSql(string $sql): array
    {
        $tables = [];
        $current = null;

        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            if ($current === null) {
                if (preg_match('/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?\s*\(/i', $line, $match)) {
                    $current = [
                        'name'       => $match[1],
                        'columns'    => [],
                        'primaryKey' => [],
                        'skipReason' => null,
                    ];
                }
                continue;
            }

            // Inside a table a line starting with ")" can only be the terminator, whatever
            // follows it (ENGINE=, DEFAULT CHARSET=, AUTO_INCREMENT=, COMMENT=, ";" ...).
            if (preg_match('/^\s*\)/', $line)) {
                $this->classifyTable($current);
                $tables[$current['name']] = $current;
                $current = null;
                continue;
            }

            if (preg_match('/^\s*PRIMARY\s+KEY\s*\(([^)]+)\)/i', $line, $match)) {
                preg_match_all('/`?([A-Za-z_][A-Za-z0-9_]*)`?/', $match[1], $keys);
                $current['primaryKey'] = $keys[1];
                continue;
            }
            // A stray CREATE TABLE inside an open table means the previous one never closed.
            if (preg_match('/^\s*CREATE\s+TABLE\b/i', $line)) {
                throw new \RuntimeException(sprintf('Table %s is not closed before the next CREATE TABLE.', $current['name']));
            }

            if (
                !preg_match(
                    '/^\s*`?([A-Za-z_][A-Za-z0-9_]*)`?\s+([A-Za-z]+)(?:\s*\([^)]*\))?\s*(.*?)(?:,\s*)?$/',
                    $line,
                    $match,
                )
            ) {
                continue;
            }

            $column = $match[1];
            if (in_array(strtoupper($column), ['PRIMARY', 'UNIQUE', 'KEY', 'INDEX', 'FULLTEXT', 'SPATIAL', 'CHECK', 'CONSTRAINT', 'FOREIGN'], true)) {
                continue;
            }

            $sqlType = strtolower($match[2]);
            $modifiers = strtoupper($match[3]);
            // `id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,` declares the key inline.
            if (str_contains($modifiers, 'PRIMARY KEY')) {
                $current['primaryKey'] = [$column];
            }
            $current['columns'][$column] = [
                'sqlType'       => $sqlType,
                'phpType'       => $this->phpType($sqlType),
                'nullable'      => !str_contains($modifiers, 'NOT NULL'),
                'autoIncrement' => str_contains($modifiers, 'AUTO_INCREMENT'),
                'dtype'         => $this->xoopsType($sqlType),
            ];
        }

        if ($current !== null) {
            $this->classifyTable($current);
            $tables[$current['name']] = $current;
        }

        return $tables;
    }

    /**
     * @param array<string, array{name:string,columns:array,primaryKey:list<string>,skipReason:?string}> $tables
     * @return array<string, array{name:string,columns:array,primaryKey:list<string>,skipReason:?string}>
     */
    public function eligibleTables(array $tables): array
    {
        return array_filter($tables, static fn (array $table): bool => $table['skipReason'] === null);
    }

    /**
     * @param array<string, array{name:string,columns:array,primaryKey:list<string>,skipReason:?string}> $tables
     * @param array<string, array{name:string,columns:array,primaryKey:list<string>,skipReason:?string}> $allTables
     * @return array{generatedTables:int,skippedTables:int,generatedFiles:int}
     */
    public function generate(
        string $modulePath,
        string $moduleDirname,
        string $moduleNamespace,
        array $tables,
        array $allTables,
    ): array {
        // Both names are interpolated into generated PHP (namespace, class names, string literals).
        foreach (['moduleDirname' => $moduleDirname, 'moduleNamespace' => $moduleNamespace] as $label => $identifier) {
            if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier)) {
                throw new \InvalidArgumentException(sprintf('%s must be a PHP identifier, "%s" given.', $label, $identifier));
            }
        }
        $modernPath = $modulePath . '/class/Modern';
        $testsPath = $modulePath . '/tests';
        $docsPath = $modulePath . '/docs/architecture';
        foreach ([$modernPath, $testsPath, $docsPath] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create bridge directory: ' . $directory);
            }
        }

        $namespace = 'XoopsModules\\' . $moduleNamespace . '\\Modern';
        $definitions = [];
        $generatedFiles = 0;

        if ($tables !== []) {
            $this->write($modernPath . '/SqlBridgeRepository.php', $this->renderSqlRepository($namespace));
            ++$generatedFiles;
        }

        foreach ($tables as $table) {
            $class = $this->className($table['name']);
            $properties = $this->propertyMap(array_keys($table['columns']));
            $key = $table['primaryKey'][0];
            $samples = [
                $this->sampleRow($table, true),
                $this->sampleRow($table, false),
            ];

            $this->write($modernPath . '/' . $class . 'Entity.php', $this->renderEntity($namespace, $class, $table, $properties));
            $this->write($modernPath . '/' . $class . 'Schema.php', $this->renderSchema($namespace, $class, $table));
            $this->write($modernPath . '/' . $class . 'Repository.php', $this->renderRepository($namespace, $class, $table));
            $this->write($modernPath . '/Bridged' . $class . 'Handler.php', $this->renderHandler($namespace, $moduleDirname, $class, $table['name'], $key));
            $generatedFiles += 4;

            $definitions[] = [
                'table'      => $table['name'],
                'entity'     => $namespace . '\\' . $class . 'Entity',
                'schema'     => $namespace . '\\' . $class . 'Schema',
                'repository' => $namespace . '\\' . $class . 'Repository',
                'handler'    => $namespace . '\\Bridged' . $class . 'Handler',
                'rows'       => $samples,
            ];
        }

        $this->write($modernPath . '/BridgeManifest.php', $this->renderManifest($namespace, $definitions));
        $this->write($testsPath . '/run-bridge-smoke.php', $this->renderSmoke($moduleDirname, $moduleNamespace));
        $this->write(
            $docsPath . '/xoops4-bridge.md',
            $this->renderDocumentation($moduleDirname, $tables, $allTables),
        );
        $generatedFiles += 3;

        return [
            'generatedTables' => count($tables),
            'skippedTables'   => count($allTables) - count($tables),
            'generatedFiles'  => $generatedFiles,
        ];
    }

    /** @param array{name:string,columns:array,primaryKey:list<string>,skipReason:?string} $table */
    private function classifyTable(array &$table): void
    {
        if ($table['primaryKey'] === []) {
            $table['skipReason'] = 'missing-primary-key';
            return;
        }
        if (count($table['primaryKey']) !== 1) {
            $table['skipReason'] = 'composite-primary-key';
            return;
        }

        $key = $table['primaryKey'][0];
        if (!isset($table['columns'][$key])) {
            $table['skipReason'] = 'primary-key-column-not-found';
            return;
        }
        if ($table['columns'][$key]['phpType'] !== 'int') {
            $table['skipReason'] = 'non-integer-primary-key';
            return;
        }
        // MySQL makes a primary key NOT NULL implicitly; a nullable key would give the entity
        // `?int $id = null`, so isNew() (`0 === $id`) and save() would disagree on a fresh row.
        $table['columns'][$key]['nullable'] = false;
    }

    private function phpType(string $sqlType): string
    {
        return match ($sqlType) {
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'year' => 'int',
            'decimal', 'numeric', 'float', 'double', 'real' => 'float',
            'bool', 'boolean' => 'bool',
            default => 'string',
        };
    }

    private function xoopsType(string $sqlType): string
    {
        return match ($this->phpType($sqlType)) {
            'int', 'bool' => 'XOBJ_DTYPE_INT',
            'float' => 'XOBJ_DTYPE_OTHER',
            default => in_array($sqlType, ['text', 'tinytext', 'mediumtext', 'longtext'], true)
                ? 'XOBJ_DTYPE_TXTAREA'
                : (in_array($sqlType, ['char', 'varchar', 'enum', 'set'], true)
                    ? 'XOBJ_DTYPE_TXTBOX'
                    : 'XOBJ_DTYPE_OTHER'),
        };
    }

    private function className(string $table): string
    {
        $name = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($table))));

        return ctype_digit($name[0] ?? '') ? 'Table' . $name : $name;
    }

    /** @param list<string> $columns @return array<string,string> */
    private function propertyMap(array $columns): array
    {
        $result = [];
        $used = [];
        foreach ($columns as $column) {
            $parts = preg_split('/_+/', $column) ?: [$column];
            $property = lcfirst(array_shift($parts) ?? 'field');
            foreach ($parts as $part) {
                $property .= ucfirst(strtolower($part));
            }
            $property = preg_replace('/[^A-Za-z0-9_]/', '', $property) ?: 'field';
            if (ctype_digit($property[0]) || 'this' === strtolower($property)) {
                $property = 'field' . ucfirst($property); // `$this` cannot be a property or parameter
            }
            $base = $property;
            $suffix = 2;
            while (isset($used[strtolower($property)])) {
                $property = $base . $suffix++;
            }
            $used[strtolower($property)] = true;
            $result[$column] = $property;
        }
        return $result;
    }

    /** @param array{name:string,columns:array,primaryKey:list<string>,skipReason:?string} $table @return array<string,mixed> */
    private function sampleRow(array $table, bool $useNulls): array
    {
        $row = [];
        $position = 1;
        foreach ($table['columns'] as $column => $metadata) {
            if ($metadata['nullable'] && $useNulls) {
                $row[$column] = null;
            } else {
                $row[$column] = match ($metadata['phpType']) {
                    'int' => $position,
                    'float' => $position + 0.25,
                    'bool' => true,
                    default => 'sample-' . $column,
                };
            }
            ++$position;
        }
        return $row;
    }

    /** @param array{name:string,columns:array,primaryKey:list<string>,skipReason:?string} $table @param array<string,string> $properties */
    private function renderEntity(string $namespace, string $class, array $table, array $properties): string
    {
        $parameters = [];
        $from = [];
        $to = [];
        foreach ($table['columns'] as $column => $metadata) {
            $property = $properties[$column];
            $type = ($metadata['nullable'] ? '?' : '') . $metadata['phpType'];
            $default = $metadata['nullable'] ? 'null' : match ($metadata['phpType']) {
                'int' => '0',
                'float' => '0.0',
                'bool' => 'false',
                default => "''",
            };
            $parameters[] = '        public ' . $type . ' $' . $property . ' = ' . $default . ',';
            $fallback = $metadata['nullable'] ? 'null' : $default;
            $value = "\$row['" . $column . "'] ?? " . $fallback;
            // Boolean columns arrive as strings from mysqli; compare numerically so any numeric zero
            // ('0', '0.0', ' 0') reads as false, not only the literal '0' that (bool) already handles.
            $cast = static fn (string $expr): string => 'bool' === $metadata['phpType']
                ? '0 !== (int) (' . $expr . ')'
                : '(' . $metadata['phpType'] . ') (' . $expr . ')';
            if ($metadata['nullable']) {
                $value = "array_key_exists('" . $column . "', \$row) && \$row['" . $column . "'] !== null"
                    . ' ? ' . $cast("\$row['" . $column . "']") . ' : null';
            } else {
                $value = $cast($value);
            }
            $from[] = '            ' . $property . ': ' . $value . ',';
            $to[] = "            '" . $column . "' => \$this->" . $property . ',';
        }

        $key = $table['primaryKey'][0];
        $keyProperty = $properties[$key];

        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n"
            . "/** Persistence-ignorant {$class} aggregate generated from the install schema. */\n"
            . "final class {$class}Entity\n{\n    public function __construct(\n"
            . implode("\n", $parameters)
            . "\n    ) {\n    }\n\n    /** @param array<string, mixed> \$row */\n"
            . "    public static function fromArray(array \$row): self\n    {\n        return new self(\n"
            . implode("\n", $from)
            . "\n        );\n    }\n\n    /** @return array<string, mixed> */\n"
            . "    public function toArray(): array\n    {\n        return [\n"
            . implode("\n", $to)
            . "\n        ];\n    }\n\n    public function isNew(): bool\n    {\n"
            . "        return 0 === \$this->{$keyProperty};\n    }\n}\n";
    }

    /** @param array{name:string,columns:array,primaryKey:list<string>,skipReason:?string} $table */
    private function renderSchema(string $namespace, string $class, array $table): string
    {
        $fields = [];
        foreach ($table['columns'] as $column => $metadata) {
            $fields[] = "                '" . $column . "' => \\" . $metadata['dtype'] . ',';
        }
        $key = $table['primaryKey'][0];
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nuse Xmf\\Bridge\\EntitySchema;\n\n"
            . "final class {$class}Schema\n{\n    public static function create(): EntitySchema\n    {\n"
            . "        return new EntitySchema(\n            keyName: '{$key}',\n            fields: [\n"
            . implode("\n", $fields)
            . "\n            ],\n            toEntity: {$class}Entity::fromArray(...),\n"
            . "            toRow: static fn ({$class}Entity \$entity): array => \$entity->toArray(),\n"
            . "        );\n    }\n}\n";
    }

    /** @param array{name:string,columns:array,primaryKey:list<string>,skipReason:?string} $table */
    private function renderRepository(string $namespace, string $class, array $table): string
    {
        $columns = "['" . implode("', '", array_keys($table['columns'])) . "']";
        $key = $table['primaryKey'][0];
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n"
            . "final class {$class}Repository extends SqlBridgeRepository\n{\n"
            . "    public function __construct(\\XoopsMySQLDatabase \$db)\n    {\n        parent::__construct(\n"
            . "            \$db,\n            '{$table['name']}',\n            '{$key}',\n            {$columns},\n"
            . "            {$class}Entity::class,\n            {$class}Entity::fromArray(...),\n"
            . "            static fn ({$class}Entity \$entity): array => \$entity->toArray(),\n"
            . "        );\n    }\n}\n";
    }

    private function renderHandler(string $namespace, string $dirname, string $class, string $table, string $key): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n"
            . "use Xmf\\Bridge\\HandlerToRepositoryBridge;\n\n"
            . "/** WRAP-stage adapter; opt in explicitly until legacy side effects are extracted. */\n"
            . "final class Bridged{$class}Handler extends HandlerToRepositoryBridge\n{\n"
            . "    public function __construct(?\\XoopsDatabase \$db = null)\n    {\n"
            . "        \$db ??= \\XoopsDatabaseFactory::getDatabaseConnection();\n"
            . "        if (!\$db instanceof \\XoopsMySQLDatabase) {\n"
            . "            throw new \\RuntimeException('{$dirname} requires XoopsMySQLDatabase.');\n        }\n"
            . "        parent::__construct(new {$class}Repository(\$db), {$class}Schema::create(), \$db, '{$table}', '{$key}');\n"
            . "    }\n}\n";
    }

    private function renderSqlRepository(string $namespace): string
    {
        $template = <<<'PHP'
<?php

declare(strict_types=1);

namespace {{NAMESPACE}};

use Xmf\Bridge\EntityRepositoryInterface;
use Xmf\Bridge\TranslatedCriteria;

/** @internal Transition-only SQL mapper shared by this module's bridge repositories. */
abstract class SqlBridgeRepository implements EntityRepositoryInterface
{
    private readonly string $table;

    /**
     * @param list<string> $columns
     * @param class-string $entityClass
     * @param \Closure(array<string,mixed>):object $fromRow
     * @param \Closure(object):array<string,mixed> $toRow
     */
    protected function __construct(
        private readonly \XoopsMySQLDatabase $db,
        string $table,
        private readonly string $key,
        private readonly array $columns,
        private readonly string $entityClass,
        private readonly \Closure $fromRow,
        private readonly \Closure $toRow,
    ) {
        $this->table = $db->prefix($table);
    }

    public function find(int $id): ?object
    {
        $result = $this->db->query(sprintf(
            'SELECT * FROM `%s` WHERE `%s` = %d LIMIT 1',
            $this->table,
            $this->key,
            $id,
        ));
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return null;
        }
        $row = $this->db->fetchArray($result);
        return is_array($row) ? ($this->fromRow)($row) : null;
    }

    public function save(object $entity, ?bool $isNew = null): object
    {
        $this->assertEntity($entity);
        $row = ($this->toRow)($entity);
        $id = (int) ($row[$this->key] ?? 0);
        if ($isNew ?? ($id === 0)) {
            $insert = $id > 0 ? $row : array_diff_key($row, [$this->key => true]);
            $columns = array_keys($insert);
            $sql = sprintf(
                'INSERT INTO `%s` (`%s`) VALUES (%s)',
                $this->table,
                implode('`, `', $columns),
                implode(', ', array_map($this->renderValue(...), array_values($insert))),
            );
            if (!$this->db->exec($sql)) {
                throw new \RuntimeException('Insert failed for ' . $this->table . ': ' . $this->db->error());
            }
            $row[$this->key] = $id > 0 ? $id : (int) $this->db->getInsertId();
            return ($this->fromRow)($row);
        }

        $assignments = [];
        foreach ($row as $column => $value) {
            if ($column !== $this->key) {
                $assignments[] = sprintf('`%s` = %s', $column, $this->renderValue($value));
            }
        }
        if ($assignments === []) {
            return $entity; // key-only table: nothing to update
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = %d',
            $this->table,
            implode(', ', $assignments),
            $this->key,
            $id,
        );
        if (!$this->db->exec($sql)) {
            throw new \RuntimeException('Update failed for ' . $this->table . ': ' . $this->db->error());
        }
        return $entity;
    }

    public function delete(object $entity): bool
    {
        $this->assertEntity($entity);
        $row = ($this->toRow)($entity);
        return $this->db->exec(sprintf(
            'DELETE FROM `%s` WHERE `%s` = %d',
            $this->table,
            $this->key,
            (int) $row[$this->key],
        ));
    }

    /** @return list<object> */
    public function findBy(TranslatedCriteria $criteria): array
    {
        $sql = 'SELECT * FROM `' . $this->table . '`' . $this->whereClause($criteria);
        if ($criteria->sort !== '') {
            $this->assertColumn($criteria->sort);
            $order = strtoupper(trim($criteria->order));
            if (!in_array($order, ['ASC', 'DESC'], true)) {
                throw new \LogicException(sprintf('%s rejects ORDER BY direction "%s".', static::class, $criteria->order));
            }
            $sql .= sprintf(' ORDER BY `%s` %s', $criteria->sort, $order);
        }
        if ($criteria->limit > 0) {
            $sql .= sprintf(' LIMIT %d OFFSET %d', $criteria->limit, $criteria->start);
        }
        $result = $this->db->query($sql);
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return [];
        }
        $entities = [];
        while (false !== ($row = $this->db->fetchArray($result))) {
            $entities[] = ($this->fromRow)($row);
        }
        return $entities;
    }

    public function countBy(TranslatedCriteria $criteria): int
    {
        $result = $this->db->query(
            'SELECT COUNT(*) AS `count` FROM `' . $this->table . '`' . $this->whereClause($criteria),
        );
        if (!$this->db->isResultSet($result) || !($result instanceof \mysqli_result)) {
            return 0;
        }
        $row = $this->db->fetchArray($result);
        return is_array($row) ? (int) ($row['count'] ?? 0) : 0;
    }

    private function whereClause(TranslatedCriteria $criteria): string
    {
        if ($criteria->isUnfiltered()) {
            return '';
        }
        $parts = [];
        foreach ($criteria->conditions as $condition) {
            $column = (string) $condition['column'];
            $this->assertColumn($column);
            $operator = strtoupper(trim((string) $condition['operator']));
            $connector = strtoupper(trim((string) ($condition['condition'] ?? 'AND')));
            if (!in_array($operator, self::OPERATORS, true) || !in_array($connector, ['AND', 'OR'], true)) {
                throw new \LogicException(sprintf('%s rejects operator "%s" / connector "%s".', static::class, $operator, $connector));
            }
            $value = in_array($operator, ['IN', 'NOT IN'], true)
                ? $this->renderList((array) $condition['value'])
                : $this->renderValue($condition['value']);
            $prefix = $parts === [] ? '' : ' ' . $connector . ' ';
            $clause = sprintf('%s`%s` %s %s', $prefix, $column, $operator, $value);
            if (in_array($operator, ['LIKE', 'NOT LIKE'], true)) {
                $clause .= ' ESCAPE ' . $this->db->quote('\\');
            }
            $parts[] = $clause;
        }
        return ' WHERE ' . implode('', $parts);
    }

    /** @param list<mixed> $values */
    private function renderList(array $values): string
    {
        return $values === []
            ? '(NULL)'
            : '(' . implode(', ', array_map($this->renderValue(...), $values)) . ')';
    }

    private function renderValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_int($value) => (string) $value,
            is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => $this->db->quote((string) $value),
        };
    }

    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    private function assertColumn(string $column): void
    {
        if (!in_array($column, $this->columns, true)) {
            throw new \LogicException(sprintf('%s column "%s" is not queryable.', static::class, $column));
        }
    }

    private function assertEntity(object $entity): void
    {
        $entityClass = $this->entityClass;
        if (!$entity instanceof $entityClass) {
            throw new \InvalidArgumentException(sprintf('%s accepts %s only.', static::class, $entityClass));
        }
    }
}
PHP;
        return str_replace('{{NAMESPACE}}', $namespace, $template);
    }

    /** @param list<array{table:string,entity:string,schema:string,repository:string,handler:string,rows:list<array>}> $definitions */
    private function renderManifest(string $namespace, array $definitions): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n"
            . "/** @generated Executable inventory for the module's XOOPS 4 bridge corpus. */\n"
            . "final class BridgeManifest\n{\n    /** @return list<array<string, mixed>> */\n"
            . "    public static function definitions(): array\n    {\n        return "
            . var_export($definitions, true)
            . ";\n    }\n}\n";
    }

    private function renderSmoke(string $dirname, string $moduleNamespace): string
    {
        $template = <<<'PHP'
<?php

declare(strict_types=1);

$xoops4Root = getenv('XOOPS4_ROOT');
$autoload = is_string($xoops4Root) && $xoops4Root !== ''
    ? rtrim(str_replace('\\', '/', $xoops4Root), '/') . '/xoops_lib/vendor/autoload.php'
    : dirname(__DIR__, 3) . '/xoops_lib/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Set XOOPS4_ROOT to an XOOPS 4 htdocs directory containing the XMF 2 autoloader.');
}
require $autoload;
if (!interface_exists(\Xmf\Bridge\EntityRepositoryInterface::class)) {
    throw new RuntimeException('The selected runtime does not provide XMF 2 Bridge contracts.');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'XoopsModules\\{{MODULE_NAMESPACE}}\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = dirname(__DIR__) . '/class/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Core's values (kernel/object.php), so a schema evaluated here matches a real XOOPS run.
defined('XOBJ_DTYPE_TXTBOX') || define('XOBJ_DTYPE_TXTBOX', 1);
defined('XOBJ_DTYPE_TXTAREA') || define('XOBJ_DTYPE_TXTAREA', 2);
defined('XOBJ_DTYPE_INT') || define('XOBJ_DTYPE_INT', 3);
defined('XOBJ_DTYPE_OTHER') || define('XOBJ_DTYPE_OTHER', 7);

$definitions = \XoopsModules\{{MODULE_NAMESPACE}}\Modern\BridgeManifest::definitions();
foreach ($definitions as $definition) {
    $entityClass = $definition['entity'];
    $schemaClass = $definition['schema'];
    $repositoryClass = $definition['repository'];
    foreach ($definition['rows'] as $row) {
        if ($entityClass::fromArray($row)->toArray() !== $row) {
            throw new RuntimeException($definition['table'] . ' entity round-trip failed.');
        }
    }
    $row = $definition['rows'][0];
    if ($schemaClass::create()->fieldNames() !== array_keys($row)) {
        throw new RuntimeException($definition['table'] . ' schema fields differ from the install schema.');
    }
    if (!is_subclass_of($repositoryClass, \Xmf\Bridge\EntityRepositoryInterface::class)) {
        throw new RuntimeException($repositoryClass . ' does not implement the XMF bridge repository contract.');
    }
}

fwrite(STDOUT, '{{DIRNAME}} bridge smoke passed (' . count($definitions) . " table(s)).\n");
PHP;
        return str_replace(
            ['{{DIRNAME}}', '{{MODULE_NAMESPACE}}'],
            [$dirname, $moduleNamespace],
            $template,
        );
    }

    /** @param array<string,array> $tables @param array<string,array> $allTables */
    private function renderDocumentation(string $dirname, array $tables, array $allTables): string
    {
        if ($allTables === []) {
            return implode("\n", [
                '# XOOPS 4 bridge status: ' . $dirname,
                '',
                'This module owns no install-schema tables, so it does not require a persistence bridge.',
                'Its remaining transition work is service/controller migration and replacement of compatibility-only helpers',
                'when stable XMF 2 owners exist. It must not claim architecture-native status until that work has executable',
                'behavioral coverage.',
                '',
                'The empty `BridgeManifest` and smoke test make the no-persistence decision explicit and reproducible.',
                '',
            ]);
        }

        $lines = [
            '# XOOPS 4 bridge status: ' . $dirname,
            '',
            'This module is **XOOPS 4 runtime-compatible, Level 1 (WRAP-ready)**. It is not yet architecture-native.',
            'The generated handlers are opt-in: existing handlers remain the default until forms, permissions, uploads,',
            'notifications, and other side effects have their own executable parity corpus.',
            '',
            '## Generated single-identity bridges',
            '',
        ];
        if ($tables === []) {
            $lines[] = 'No install-schema table satisfies the current XMF bridge identity contract.';
        } else {
            foreach ($tables as $table) {
                $lines[] = '- `' . $table['name'] . '` — key `' . $table['primaryKey'][0] . '`.';
            }
        }
        $lines[] = '';
        $lines[] = '## Explicitly deferred tables';
        $lines[] = '';
        $skipped = array_filter($allTables, static fn (array $table): bool => $table['skipReason'] !== null);
        if ($skipped === []) {
            $lines[] = 'None.';
        } else {
            foreach ($skipped as $table) {
                $lines[] = '- `' . $table['name'] . '` — ' . $table['skipReason'] . '.';
            }
        }
        $lines[] = '';
        $lines[] = 'Run `tests/run-bridge-smoke.php` with `XOOPS4_ROOT` pointing to the XOOPS 4 `htdocs` directory.';
        $lines[] = '';
        return implode("\n", $lines);
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write generated bridge file: ' . $path);
        }
    }
}
