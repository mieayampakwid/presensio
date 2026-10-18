<?php

namespace App\Console\Commands;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Services\Notifications\FeeNotifier;
use App\Services\SchoolSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

#[Signature('fees:send-bill-reminders')]
#[Description('Remind guardians of bills falling due in the configured number of days')]
class SendBillRemindersCommand extends Command
{
    /**
     * Daily reminder job (spec 17 Req 6). Idempotent: the dispatcher dedupes per guardian per day.
     */
    public function handle(SchoolSettings $settings, FeeNotifier $notifier): int
    {
        $today = $settings->todayDate();
        $dueDate = Date::parse($today)->addDays($settings->billReminderDaysBefore())->toDateString();

        $bills = Bill::query()
            ->whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
            ->whereNull('cancelled_at')
            ->whereNull('written_off_at')
            ->whereDate('due_date', $dueDate)
            ->with('student.guardians.user')
            ->get();

        $notifier->billsDue($bills, $today);

        $this->info("Processed {$bills->count()} bills due on {$dueDate}.");

        return self::SUCCESS;
    }
}
