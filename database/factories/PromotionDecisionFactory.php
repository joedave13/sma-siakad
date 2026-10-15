<?php

namespace Database\Factories;

use App\Enums\PromotionOutcome;
use App\Models\Classroom;
use App\Models\PromotionBatch;
use App\Models\PromotionDecision;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionDecision>
 */
class PromotionDecisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_batch_id' => PromotionBatch::factory(),
            'student_id' => Student::factory(),
            'from_classroom_id' => Classroom::factory(),
            'to_classroom_id' => null,
            'outcome' => PromotionOutcome::Promote,
            'is_overridden' => false,
            'override_reason' => null,
        ];
    }

    /**
     * Indicate that the decision was manually overridden by an admin.
     */
    public function overridden(string $reason): static
    {
        return $this->state(fn (array $attributes) => [
            'is_overridden' => true,
            'override_reason' => $reason,
        ]);
    }
}
