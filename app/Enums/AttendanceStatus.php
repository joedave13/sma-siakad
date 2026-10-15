<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Sick = 'sick';
    case Permitted = 'permitted';
    case Absent = 'absent';
    case Late = 'late';

    /**
     * Indonesian label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Sick => 'Sakit',
            self::Permitted => 'Izin',
            self::Absent => 'Alpa',
            self::Late => 'Terlambat',
        };
    }

    /**
     * The daily-status value used by DailyAttendance (Late becomes Present + is_late flag).
     */
    public function dailyValue(): self
    {
        return $this === self::Late ? self::Present : $this;
    }
}
