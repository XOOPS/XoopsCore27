<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Admin;

use Xoops\ModuleTools\Internal\Presentation\ObjectValuePresenter;

/** Streams a standards-compliant UTF-8 CSV export without temporary files. */
final class Export
{
    private array|false $outputMethods = false;
    private array $excluded = [];

    public function __construct(
        private readonly \XoopsPersistableObjectHandler $handler,
        private readonly ?\CriteriaElement $criteria = null,
        private readonly array|false $fields = false,
    ) {
    }

    public function setOuptutMethods(array $methods): void
    {
        $this->outputMethods = $methods;
    }
    public function setNotDisplayFields(array|string $fields): void
    {
        $this->excluded = array_values(array_unique([...$this->excluded, ...(array) $fields]));
    }

    public function render(string $filename): never
    {
        if (headers_sent()) {
            throw new \RuntimeException('CSV export headers have already been sent.');
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($filename)) ?: 'export.csv';
        if (!str_ends_with(strtolower($safeName), '.csv')) {
            $safeName .= '.csv';
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Cache-Control: private, no-store');
        $stream = fopen('php://output', 'wb');
        if (false === $stream) {
            throw new \RuntimeException('Unable to open the CSV output stream.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        $headerWritten = false;
        foreach ($this->handler->getObjects($this->criteria) as $object) {
            $keys = array_values(array_filter(array_keys($object->vars), fn (string $key): bool => (false === $this->fields || in_array($key, $this->fields, true)) && !in_array($key, $this->excluded, true)));
            if (!$headerWritten) {
                fputcsv($stream, array_map(fn (string $key): string => self::csvCell((string) ($object->vars[$key]['form_caption'] ?? $key)), $keys), escape: '');
                $headerWritten = true;
            }
            $row = [];
            foreach ($keys as $key) {
                $method = false !== $this->outputMethods ? ($this->outputMethods[$key] ?? null) : null;
                // A CSV has no HTML context: stored values, never entities or rendered markup.
                $value = is_string($method) && method_exists($object, $method) ? $object->{$method}() : ObjectValuePresenter::plain($object, $key);
                $row[] = self::csvCell(is_array($value) ? implode(', ', array_map(strval(...), $value)) : (string) $value);
            }
            fputcsv($stream, $row, escape: '');
        }
        fclose($stream);
        exit;
    }

    /** Neutralize formula prefixes, including tab/CR/LF, with a leading quote. */
    private static function csvCell(string $value): string
    {
        // Only a genuinely negative number keeps its sign; "+1" is still a formula to a spreadsheet.
        return '' !== $value && str_contains("=+-@\t\r\n", $value[0]) && !('-' === $value[0] && is_numeric($value)) ? "'" . $value : $value;
    }
}
