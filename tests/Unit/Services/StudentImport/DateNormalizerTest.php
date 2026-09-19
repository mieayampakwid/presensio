<?php

namespace Tests\Unit\Services\StudentImport;

use App\Services\StudentImport\DateNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DateNormalizerTest extends TestCase
{
    #[DataProvider('parseableDates')]
    public function test_parses_supported_date_formats(string $input, string $expected): void
    {
        $this->assertSame($expected, DateNormalizer::normalize($input));
    }

    #[DataProvider('unparseableDates')]
    public function test_rejects_unparseable_or_implausible_dates(string $input): void
    {
        $this->assertNull(DateNormalizer::normalize($input));
    }

    public static function parseableDates(): array
    {
        return [
            'day-first slash' => ['17/05/2012', '2012-05-17'],
            'day-first single digit' => ['3/5/2012', '2012-05-03'],
            'day-first dash' => ['17-05-2012', '2012-05-17'],
            'iso' => ['2012-05-17', '2012-05-17'],
            'excel serial 45000' => ['45000', '2023-03-15'],
            'old but plausible' => ['31/12/1999', '1999-12-31'],
        ];
    }

    public static function unparseableDates(): array
    {
        return [
            'garbage' => ['not a date'],
            'empty' => [''],
            'us-style month first' => ['12/31/1999'],
            'future date' => ['01/01/2099'],
            'serial below range' => ['123'],
            'serial above range' => ['99999'],
            'iso with time garbage' => ['2012-05-17 99:99'],
        ];
    }
}
