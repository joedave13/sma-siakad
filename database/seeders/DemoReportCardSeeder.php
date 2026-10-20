<?php

namespace Database\Seeders;

use App\Enums\GenerationStatus;
use App\Enums\StudentStatus;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\ReportCard\PublishService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;

class DemoReportCardSeeder extends Seeder
{
    /**
     * Generate every report card for the active semester through the real
     * bulk flow, queue the PDFs, and publish the complete ones so student
     * and guardian rapor views have data.
     */
    public function run(): void
    {
        $semester = Semester::query()->where('is_active', true)->firstOrFail();
        $ordinals = DemoData::ordinalsByNisn();
        $students = Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $semester->academic_year_id))
            ->get();

        foreach ($students as $student) {
            $ordinal = $ordinals[$student->nisn] ?? null;

            ReportCard::query()->firstOrCreate(
                ['student_id' => $student->getKey(), 'semester_id' => $semester->getKey()],
                ['homeroom_note' => $ordinal === null ? null : DemoData::homeroomNoteFor($ordinal)],
            );
        }

        $result = app(PublishService::class)->generateBulk($semester);

        Artisan::call('queue:work', ['--stop-when-empty' => true, '--force' => true]);

        $published = 0;
        foreach (ReportCard::query()->where('semester_id', $semester->getKey())->get() as $card) {
            if ($card->generation_status !== GenerationStatus::Done) {
                continue;
            }

            if ($card->published_at === null) {
                $card->forceFill(['published_at' => Date::now()])->save();
            }

            $published++;
        }

        $semester->update(['report_published_at' => Date::now()]);

        AuditLogger::record('report_card.publish', $semester, [], ['published' => $published]);

        $this->command->info(sprintf(
            'Report cards: %d queued, %d published, %d skipped for missing grades.',
            $result['queued'],
            $published,
            count($result['skipped']),
        ));
    }
}
