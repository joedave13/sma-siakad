<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\ReportCard;
use App\Services\ReportCard\ReportCardPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateReportCardPdf implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $reportCardId) {}

    /**
     * Execute the job.
     *
     * A failed render never touches the stored file: the PDF is fully built
     * in memory before the storage write, so a published PDF survives failures.
     */
    public function handle(ReportCardPdfService $pdfService): void
    {
        $reportCard = ReportCard::query()->with(['student', 'semester'])->findOrFail($this->reportCardId);

        try {
            $fresh = $pdfService->generate($reportCard->student, $reportCard->semester);
            $fresh->forceFill([
                'generation_status' => GenerationStatus::Done,
                'generation_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $reportCard->forceFill([
                'generation_status' => GenerationStatus::Failed,
                'generation_error' => $exception->getMessage(),
            ])->save();
        }
    }
}
