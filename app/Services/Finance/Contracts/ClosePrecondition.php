<?php

namespace App\Services\Finance\Contracts;

use App\Models\AccountingPeriod;

interface ClosePrecondition
{
    /**
     * Verify whether the accounting period meets the condition to be closed.
     *
     * @throws \RuntimeException
     */
    public function check(AccountingPeriod $period): void;
}
