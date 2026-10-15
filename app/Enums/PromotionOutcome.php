<?php

namespace App\Enums;

enum PromotionOutcome: string
{
    case Promote = 'promote';
    case Retain = 'retain';
    case Graduate = 'graduate';

    /**
     * Indonesian label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Promote => 'Naik Kelas',
            self::Retain => 'Tinggal Kelas',
            self::Graduate => 'Lulus',
        };
    }
}
