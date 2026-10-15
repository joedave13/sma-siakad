<?php

namespace App\Enums;

enum ClassMajor: string
{
    case Ipa = 'ipa';
    case Ips = 'ips';

    /**
     * Indonesian label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ipa => 'IPA',
            self::Ips => 'IPS',
        };
    }
}
