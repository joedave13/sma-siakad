<?php

namespace App\Enums;

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
}
