<?php

namespace Database\Factories;

use App\Enums\PromotionBatchStatus;
use App\Models\AcademicYear;
use App\Models\PromotionBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionBatch>
 */
class PromotionBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_academic_year_id' => AcademicYear::factory(),
            'to_academic_year_id' => AcademicYear::factory(),
            'status' => PromotionBatchStatus::Draft,
            'applied_by' => null,
            'applied_at' => null,
        ];
    }
}
