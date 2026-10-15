<?php

namespace App\Enums;

enum SemesterTerm: string
{
    case Ganjil = 'ganjil';
    case Genap = 'genap';

    /**
     * Indonesian label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ganjil => 'Ganjil',
            self::Genap => 'Genap',
        };
    }
}
