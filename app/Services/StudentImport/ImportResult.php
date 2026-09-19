<?php

namespace App\Services\StudentImport;

/**
 * Outcome of a preview (dry-run) or committed import run.
 */
class ImportResult
{
    /**
     * @param  int  $validCount  Rows ready to import (preview) or committed (run).
     * @param  list<array{row: int, errors: list<string>}>  $errors  Per-row failures, 1-based row numbers.
     * @param  list<array<string, string|null>>  $rows  Sample of valid rows for display.
     */
    public function __construct(
        public readonly int $validCount,
        public readonly array $errors,
        public readonly array $rows = [],
    ) {}
}
