<?php

namespace Database\Factories;

use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassEnrollment>
 */
class ClassEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => fn (array $attributes) => Classroom::query()
                ->findOrFail((int) $attributes['classroom_id'])
                ->getAttribute('academic_year_id'),
        ];
    }
}
