<?php

namespace App\Enums;

enum Role: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    case Admin = 'admin';
    case Parent = 'parent';

    /**
     * Indonesian label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Student => 'Siswa',
            self::Teacher => 'Guru',
            self::Admin => 'Staf Admin',
            self::Parent => 'Orang Tua',
        };
    }
}
