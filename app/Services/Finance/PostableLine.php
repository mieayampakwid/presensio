<?php

namespace App\Services\Finance;

final class PostableLine
{
    public function __construct(
        public int $accountId,
        public string|int|float $debit = '0.00',
        public string|int|float $credit = '0.00',
        public ?int $studentId = null,
        public ?int $billId = null,
        public ?string $memo = null,
    ) {}
}
