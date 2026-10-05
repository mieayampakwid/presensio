<?php

namespace App\Enums;

enum EmployeeAttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Sick = 'sick';
    case Leave = 'leave';
    case AnnualLeave = 'annual_leave';
    case OfficialDuty = 'official_duty';

    /**
     * Check if this status counts as excused.
     */
    public function isExcused(): bool
    {
        return match ($this) {
            self::Sick, self::Leave, self::AnnualLeave, self::OfficialDuty => true,
            default => false,
        };
    }

    /**
     * Human-readable label in Indonesian.
     */
    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Absent => 'Alpa',
            self::Sick => 'Sakit',
            self::Leave => 'Izin',
            self::AnnualLeave => 'Cuti',
            self::OfficialDuty => 'Dinas Luar',
        };
    }
}
