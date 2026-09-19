<?php

namespace App\Services\StudentImport;

use Generator;

/**
 * CSV reader with defensive defaults: sniffs the delimiter (`,` `;` tab),
 * strips a UTF-8/UTF-16 BOM, converts Windows-1252 to UTF-8, and returns
 * every cell as a trimmed string.
 */
class CsvRowReader implements RowReader
{
    /** @var resource|null */
    private $handle;

    /** @var list<string>|null */
    private ?array $headers = null;

    private readonly string $delimiter;

    /**
     * @param  resource  $handle  Position at the start of the file.
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
        $this->delimiter = $this->sniffDelimiter();
        $this->consumeHeaderRow();
    }

    public function headers(): array
    {
        return $this->headers ?? [];
    }

    public function rows(): Generator
    {
        while (($row = $this->readRow()) !== null) {
            yield $row;
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', implode("\x1f", $this->headers()));
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    /**
     * Read the first data line with each candidate delimiter and keep the
     * one producing the most fields.
     */
    private function sniffDelimiter(): string
    {
        if ($this->handle === null) {
            return ',';
        }

        $position = ftell($this->handle);
        $line = (string) fgets($this->handle);
        fseek($this->handle, (int) $position);

        $best = ',';
        $bestCount = 1;

        foreach ([',', ';', "\t"] as $candidate) {
            $count = count(str_getcsv($line, $candidate));

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function consumeHeaderRow(): void
    {
        $row = $this->readRow();

        $this->headers = $row ?? [];
    }

    /**
     * @return list<string>|null
     */
    private function readRow(): ?array
    {
        if ($this->handle === null) {
            return null;
        }

        do {
            $line = fgets($this->handle);

            if ($line === false) {
                return null;
            }

            $line = $this->decodeLine($line);
            $row = str_getcsv($line, $this->delimiter);
            $row = array_map(
                fn (?string $cell): string => trim((string) $cell),
                $row,
            );

            // Skip fully empty lines.
        } while ($row === ['']);

        return $row;
    }

    private function decodeLine(string $line): string
    {
        $line = $this->stripBom($line);

        if (! mb_check_encoding($line, 'UTF-8')) {
            $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1252');
        }

        return $line;
    }

    private function stripBom(string $line): string
    {
        $boms = [
            "\xEF\xBB\xBF" => '',
            "\xFF\xFE" => '', // UTF-16LE
            "\xFE\xFF" => '', // UTF-16BE
        ];

        foreach ($boms as $bom => $replacement) {
            if (str_starts_with($line, $bom)) {
                $line = substr_replace($line, $replacement, 0, strlen($bom));
                break;
            }
        }

        return $line;
    }
}
