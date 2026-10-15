<?php

namespace App\Services\ReportCard;

use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReportCardPdfService
{
    public function __construct(private ReportCardSnapshotBuilder $snapshotBuilder) {}

    /**
     * Generate (or regenerate) the report card PDF for one student and semester.
     *
     * The snapshot is stored at generate time; later identity changes never
     * touch already-stored snapshots.
     */
    public function generate(Student $student, Semester $semester): ReportCard
    {
        $snapshot = $this->snapshotBuilder->build($student, $semester);

        $pdf = Pdf::loadView('pdf.report-card', ['snapshot' => $snapshot])
            ->setPaper('a4', 'portrait')
            ->output();

        $path = sprintf('report-cards/%d-%d.pdf', $student->getKey(), $semester->getKey());
        Storage::disk('public')->put($path, $pdf);

        $reportCard = ReportCard::query()->firstOrNew([
            'student_id' => $student->getKey(),
            'semester_id' => $semester->getKey(),
        ]);

        $reportCard->snapshot = $snapshot;
        $reportCard->pdf_path = $path;
        $reportCard->generated_at = now();
        $reportCard->save();

        return $reportCard;
    }
}
