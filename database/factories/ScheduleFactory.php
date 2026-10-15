<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teaching_assignment_id' => TeachingAssignment::factory(),
            'day_of_week' => fake()->randomElement(DayOfWeek::cases()),
            'lesson_period_id' => LessonPeriod::factory(),
        ];
    }
}
