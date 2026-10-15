<?php

namespace Database\Seeders;

use App\Models\LessonPeriod;
use Illuminate\Database\Seeder;

class LessonPeriodSeeder extends Seeder
{
    /**
     * The ten default lesson periods of the school day (07:00 - 15:30).
     */
    private const array PERIODS = [
        ['number' => 1, 'start_time' => '07:00', 'end_time' => '07:45'],
        ['number' => 2, 'start_time' => '07:45', 'end_time' => '08:30'],
        ['number' => 3, 'start_time' => '08:30', 'end_time' => '09:15'],
        ['number' => 4, 'start_time' => '09:35', 'end_time' => '10:20'],
        ['number' => 5, 'start_time' => '10:20', 'end_time' => '11:05'],
        ['number' => 6, 'start_time' => '11:05', 'end_time' => '11:50'],
        ['number' => 7, 'start_time' => '12:30', 'end_time' => '13:15'],
        ['number' => 8, 'start_time' => '13:15', 'end_time' => '14:00'],
        ['number' => 9, 'start_time' => '14:00', 'end_time' => '14:45'],
        ['number' => 10, 'start_time' => '14:45', 'end_time' => '15:30'],
    ];

    /**
     * Seed the default lesson periods (idempotent).
     */
    public function run(): void
    {
        foreach (self::PERIODS as $period) {
            LessonPeriod::query()->updateOrCreate(['number' => $period['number']], $period);
        }
    }
}
