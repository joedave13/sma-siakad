<?php

namespace Database\Factories;

use App\Enums\ClassMajor;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gradeLevel = fake()->numberBetween(10, 12);
        $roman = [10 => 'X', 11 => 'XI', 12 => 'XII'][$gradeLevel];
        $stream = $gradeLevel === 10 ? '' : '-'.fake()->randomElement(['IPA', 'IPS']);

        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => "{$roman}{$stream}-".fake()->unique()->numberBetween(1, 99),
            'grade_level' => $gradeLevel,
            'major' => $gradeLevel === 10 ? null : fake()->randomElement([ClassMajor::Ipa, ClassMajor::Ips]),
            'homeroom_teacher_id' => Teacher::factory(),
        ];
    }
}
