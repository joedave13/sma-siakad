<?php

namespace Database\Seeders;

use App\Enums\PromotionOutcome;
use App\Models\AcademicYear;
use App\Models\PromotionBatch;
use App\Models\PromotionDecision;
use App\Models\Student;
use App\Services\Promotion\PromotionService;
use Illuminate\Database\Seeder;

class DemoPromotionSeeder extends Seeder
{
    /**
     * Seed the next-year promotion preview with a real proposal, one
     * overridden outcome, and audit entries from the admin session.
     */
    public function run(): void
    {
        $fromYear = AcademicYear::query()->where('name', DemoData::CURRENT_YEAR)->firstOrFail();
        $toYear = AcademicYear::query()->where('name', DemoData::NEXT_YEAR)->firstOrFail();
        $service = app(PromotionService::class);

        $result = $service->propose($fromYear, $toYear);

        $student = Student::query()->where('nisn', DemoData::nisnFor(13))->first();

        if ($student !== null) {
            $decision = PromotionDecision::query()
                ->where('promotion_batch_id', $result['batch']->getKey())
                ->where('student_id', $student->getKey())
                ->first();

            if ($decision !== null && $decision->outcome !== PromotionOutcome::Retain) {
                $service->override(
                    $decision,
                    PromotionOutcome::Retain,
                    'Perlu remedial mata pelajaran sebelum lulus.',
                );
            }
        }

        $this->command->info(sprintf(
            'Promotion batch: %d promote, %d retain, %d graduate.',
            $this->outcomeCount($result['batch'], PromotionOutcome::Promote),
            $this->outcomeCount($result['batch'], PromotionOutcome::Retain),
            $this->outcomeCount($result['batch'], PromotionOutcome::Graduate),
        ));
    }

    /**
     * The number of decisions in one batch with the given outcome.
     */
    private function outcomeCount(PromotionBatch $batch, PromotionOutcome $outcome): int
    {
        return PromotionDecision::query()
            ->where('promotion_batch_id', $batch->getKey())
            ->where('outcome', $outcome->value)
            ->count();
    }
}
