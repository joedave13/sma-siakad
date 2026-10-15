<?php

namespace Database\Factories;

use App\Models\GradeRemark;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeRemark>
 */
class GradeRemarkFactory extends Factory
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
            'remark' => fake()->sentence(),
        ];
    }
}
