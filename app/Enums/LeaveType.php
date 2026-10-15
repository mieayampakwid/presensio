<?php

namespace App\Enums;

enum LeaveType: string
{
    case Sick = 'sick';
    case Leave = 'leave';
    case AnnualLeave = 'annual_leave';
    case OfficialDuty = 'official_duty';

    /**
     * Human-readable label in Indonesian, matching the
     * EmployeeAttendanceStatus vocabulary the recap reuses.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Sakit',
            self::Leave => 'Izin',
            self::AnnualLeave => 'Cuti',
            self::OfficialDuty => 'Dinas Luar',
        };
    }
}
