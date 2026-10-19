<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum DayOfWeek: string
{
    case Senin = 'senin';
    case Selasa = 'selasa';
    case Rabu = 'rabu';
    case Kamis = 'kamis';
    case Jumat = 'jumat';
    case Sabtu = 'sabtu';

    /**
     * Indonesian label for UI display.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The school day for a calendar date; Sunday is not a school day.
     */
    public static function fromDate(CarbonInterface $date): ?self
    {
        return match ((int) $date->format('w')) {
            1 => self::Senin,
            2 => self::Selasa,
            3 => self::Rabu,
            4 => self::Kamis,
            5 => self::Jumat,
            6 => self::Sabtu,
            default => null,
        };
    }
}
