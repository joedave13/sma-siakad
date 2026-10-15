<?php

namespace App\Enums;

enum GradeComponent: string
{
    case Assignment = 'assignment';
    case Quiz = 'quiz';
    case Midterm = 'midterm';
    case Final = 'final';

    /**
     * Indonesian label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Assignment => 'Tugas',
            self::Quiz => 'Quiz',
            self::Midterm => 'UTS',
            self::Final => 'UAS',
        };
    }

    /**
     * The component's weight in the final score (T2 AC: 30/20/20/30).
     */
    public function weight(): float
    {
        return match ($this) {
            self::Assignment => 0.30,
            self::Quiz => 0.20,
            self::Midterm => 0.20,
            self::Final => 0.30,
        };
    }

    /**
     * Components that may only have one entry per student per assignment.
     */
    public function isSingular(): bool
    {
        return in_array($this, [self::Midterm, self::Final], true);
    }
}
