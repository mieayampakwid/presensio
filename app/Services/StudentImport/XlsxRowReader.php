<?php

namespace App\Services\StudentImport;

use DateTimeInterface;
use Generator;
use OpenSpout\Reader\XLSX\Reader;

/**
 * XLSX reader over the first sheet. All cells are stringified — numeric
 * cells come out as plain digits, preserving leading zeros only when Excel
 * stored them as text (which is exactly what we want to enforce on input).
 */
class XlsxRowReader implements RowReader
{
    private readonly Reader $reader;

    /** @var list<string>|null */
    private ?array $headers = null;

    public function __construct(string $path)
    {
        $this->reader = new Reader;
        $this->reader->open($path);
        $this->consumeHeaderRow();
    }

    public function headers(): array
    {
        return $this->headers ?? [];
    }

    public function rows(): Generator
    {
        $sheetIterator = $this->reader->getSheetIterator();
        $sheetIterator->rewind();
        $sheet = $sheetIterator->current();

        $first = true;

        foreach ($sheet->getRowIterator() as $row) {
            if ($first) {
                $first = false;

                continue;
            }

            $cells = $this->stringifyRow($row->toArray());

            if ($cells !== ['']) {
                yield $cells;
            }
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode("\x1f", $this->headers()));
    }

    public function close(): void
    {
        $this->reader->close();
    }

    private function consumeHeaderRow(): void
    {
        $sheetIterator = $this->reader->getSheetIterator();
        $sheetIterator->rewind();
        $sheet = $sheetIterator->current();

        foreach ($sheet->getRowIterator() as $row) {
            $this->headers = $this->stringifyRow($row->toArray());

            return;
        }

        $this->headers = [];
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function stringifyRow(array $values): array
    {
        $cells = [];

        foreach ($values as $value) {
            $cells[] = trim($this->stringify($value));
        }

        return $cells;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '', '');
        }

        return (string) $value;
    }
}
