<?php

namespace App\Services\Staff;

use App\Models\ShiftRoster;
use Illuminate\Support\Facades\DB;

class ShiftRosterService
{
    /**
     * Upsert one roster row per (employee, date) in the range.
     *
     * @param  array<int, int>  $employeeIds
     * @param  list<string>  $dates
     */
    public function bulkAssign(array $employeeIds, array $dates, ?int $shiftId): void
    {
        $rows = [];
        foreach ($employeeIds as $employeeId) {
            foreach ($dates as $date) {
                $rows[] = [
                    'employee_id' => $employeeId,
                    'date' => $date,
                    'shift_id' => $shiftId,
                ];
            }
        }

        DB::transaction(fn (): int => ShiftRoster::upsert($rows, ['employee_id', 'date'], ['shift_id']));
    }

    /**
     * Override one cell: a shift, or an explicit day off when shift_id is null (spec 20 §Req 3).
     */
    public function setCell(int $employeeId, string $date, ?int $shiftId): void
    {
        ShiftRoster::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'date' => $date,
            ],
            ['shift_id' => $shiftId],
        );
    }

    /**
     * Remove a roster row — the cell falls back to the default shift (AC-20-06).
     */
    public function clearCell(int $employeeId, string $date): void
    {
        ShiftRoster::query()
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->delete();
    }
}
