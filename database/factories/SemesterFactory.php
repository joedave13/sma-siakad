<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => fake()->randomElement(['ganjil', 'genap']),
            'is_active' => false,
        ];
    }

    /**
     * Indicate that the semester is the active one.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate the Ganjil (odd) semester.
     */
    public function ganjil(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'ganjil',
        ]);
    }

    /**
     * Indicate the Genap (even) semester.
     */
    public function genap(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'genap',
        ]);
    }
}
