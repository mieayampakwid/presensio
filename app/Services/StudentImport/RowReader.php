<?php

namespace App\Services\StudentImport;

use Generator;

/**
 * A row-oriented reader over an uploaded spreadsheet file. All cell values
 * are returned as trimmed strings — identifiers like NIS "007" or a 16-digit
 * NIK must never be numeric-cast.
 */
interface RowReader
{
    /**
     * The header row, as trimmed strings.
     *
     * @return list<string>
     */
    public function headers(): array;

    /**
     * Data rows (headers excluded), keyed by ordinal position starting at 0.
     *
     * @return Generator<list<string>>
     */
    public function rows(): Generator;

    /**
     * Stable fingerprint of the normalized header row (sha256), used to
     * remember column mappings across uploads.
     */
    public function fingerprint(): string;

    /**
     * Release the underlying file handle.
     */
    public function close(): void;
}
