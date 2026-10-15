<?php

namespace Database\Factories;

use App\Enums\GradeComponent;
use App\Models\GradeEntry;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeEntry>
 */
class GradeEntryFactory extends Factory
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
            'student_id' => Student::factory(),
            'component' => GradeComponent::Assignment,
            'title' => 'Tugas 1',
            'score' => fake()->randomFloat(2, 0, 100),
        ];
    }
}
