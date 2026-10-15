<?php

namespace Database\Factories;

use App\Models\LessonPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<LessonPeriod>
 */
class LessonPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 10);
        $start = Carbon::parse('07:00')->addMinutes(($number - 1) * 50);

        return [
            'number' => $number,
            'start_time' => $start->format('H:i'),
            'end_time' => $start->copy()->addMinutes(45)->format('H:i'),
        ];
    }
}
