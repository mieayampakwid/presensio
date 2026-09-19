<?php

namespace App\Services\StudentImport;

/**
 * Maps raw spreadsheet headers onto canonical import fields.
 *
 * Pass 1: exact matches, then prefix matches against the alias dictionary.
 * Pass 2: fuzzy matching (similar_text >= 80% or Levenshtein <= 2).
 * Each spreadsheet column is consumed at most once; each field maps to at
 * most one column (first match wins). Returned values are the raw headers,
 * so callers can locate the column in the reader's header row.
 */
class ColumnMapper
{
    private const MIN_FUZZY_PERCENT = 80;

    private const MAX_LEVENSHTEIN_DISTANCE = 2;

    /**
     * @param  list<string>  $headers
     * @return array<string, string> canonical field => raw header
     */
    public function map(array $headers): array
    {
        $aliases = ColumnDictionary::aliases();
        $owner = []; // header index => field

        $pass = function (callable $matcher) use ($headers, $aliases, &$owner): void {
            foreach ($headers as $index => $header) {
                if (isset($owner[$index]) || trim($header) === '') {
                    continue;
                }

                $normalized = $this->normalize($header);

                foreach ($aliases as $field => $aliasList) {
                    if (in_array($field, $owner, true)) {
                        continue;
                    }

                    foreach ($aliasList as $alias) {
                        if ($matcher($normalized, $alias)) {
                            $owner[$index] = $field;

                            break 2;
                        }
                    }
                }
            }
        };

        $pass(fn (string $header, string $alias): bool => $header === $alias);
        $pass(fn (string $header, string $alias): bool => str_starts_with($header, $alias.' '));
        $pass(fn (string $header, string $alias): bool => $this->isFuzzyMatch($header, $alias));

        $mapped = [];

        foreach ($owner as $index => $field) {
            $mapped[$field] = $headers[$index];
        }

        return $mapped;
    }

    private function isFuzzyMatch(string $header, string $alias): bool
    {
        if (levenshtein($header, $alias) <= self::MAX_LEVENSHTEIN_DISTANCE) {
            return true;
        }

        similar_text($header, $alias, $percent);

        return $percent >= self::MIN_FUZZY_PERCENT;
    }

    private function normalize(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9 ]+/', ' ', $header) ?? $header;

        return trim((string) preg_replace('/\s+/', ' ', $header));
    }
}
