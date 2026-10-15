<?php

namespace App\Enums;

enum PromotionBatchStatus: string
{
    case Draft = 'draft';
    case Applied = 'applied';

    /**
     * Indonesian label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Applied => 'Diterapkan',
        };
    }
}
