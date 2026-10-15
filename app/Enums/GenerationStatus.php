<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Pending = 'pending';
    case Failed = 'failed';
    case Done = 'done';

    /**
     * Indonesian label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Failed => 'Gagal',
            self::Done => 'Selesai',
        };
    }
}
