<?php

namespace App\Services\ReportCard;

use App\Enums\GenerationStatus;
use App\Enums\StudentStatus;
use App\Jobs\GenerateReportCardPdf;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublishService
{
    public function __construct(private ReportCardSnapshotBuilder $snapshotBuilder) {}

    /**
     * Lock a semester: blocks all teacher grade edits (T2 AC).
     */
    public function lock(Semester $semester, User $admin): void
    {
        $semester->update(['grades_locked_at' => now()]);

        AuditLogger::record('semester.lock', $semester, [], ['grades_locked_at' => $semester->grades_locked_at?->toIso8601String()]);
    }

    /**
     * Unlock a semester: requires a reason and is audit-logged (A4 AC).
     *
     * @throws ValidationException
     */
    public function unlock(Semester $semester, string $reason, User $admin): void
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pembukaan kunci wajib diisi.',
            ]);
        }

        $previous = $semester->grades_locked_at?->toIso8601String();
        $semester->update(['grades_locked_at' => null]);

        AuditLogger::record('semester.unlock', $semester, ['grades_locked_at' => $previous], ['reason' => $reason]);
    }

    /**
     * Queue one PDF generation job per enrolled student (status pending/failed/done).
     *
     * Students with missing components are marked failed with the listing and
     * skipped; the rest are queued. Per-student failures never stop the others.
     *
     * @return array{queued: int, skipped: array<int, array{student: string, missing: array<int, string>}>}
     */
    public function generateBulk(Semester $semester): array
    {
        $this->snapshotBuilder->assertUsableIdentity();

        $queued = 0;
        $skipped = [];

        foreach ($this->students($semester) as $student) {
            $missing = $this->snapshotBuilder->missingComponentsFor($student, $semester);

            $reportCard = ReportCard::query()->firstOrCreate([
                'student_id' => $student->getKey(),
                'semester_id' => $semester->getKey(),
            ]);

            if ($missing !== []) {
                $reportCard->forceFill([
                    'generation_status' => GenerationStatus::Failed,
                    'generation_error' => 'Nilai belum lengkap: '.implode(', ', $missing),
                ])->save();
                $skipped[] = ['student' => $student->full_name, 'missing' => $missing];

                continue;
            }

            $reportCard->forceFill([
                'generation_status' => GenerationStatus::Pending,
                'generation_error' => null,
            ])->save();

            GenerateReportCardPdf::dispatch($reportCard->getKey());
            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * Re-queue every failed generation for the semester.
     */
    public function retryFailed(Semester $semester): int
    {
        $failed = ReportCard::query()
            ->where('semester_id', $semester->getKey())
            ->where('generation_status', GenerationStatus::Failed->value)
            ->pluck('id');

        foreach ($failed as $reportCardId) {
            ReportCard::query()->whereKey($reportCardId)->update([
                'generation_status' => GenerationStatus::Pending->value,
                'generation_error' => null,
            ]);
            GenerateReportCardPdf::dispatch($reportCardId);
        }

        return $failed->count();
    }

    /**
     * Publish every generated report card for the semester (A4 AC).
     *
     * Blocked when the school identity is incomplete or any active enrolled
     * student is missing components or a generated PDF (listing included).
     *
     *
     * @return array{published: int}
     *
     * @throws ValidationException
     */
    public function publish(Semester $semester, User $admin): array
    {
        $this->snapshotBuilder->assertUsableIdentity();

        $blockers = [];
        $cards = [];

        foreach ($this->students($semester) as $student) {
            $missing = $this->snapshotBuilder->missingComponentsFor($student, $semester);

            if ($missing !== []) {
                $blockers[] = ['student' => $student->full_name, 'reason' => 'Nilai belum lengkap: '.implode(', ', $missing)];

                continue;
            }

            $reportCard = ReportCard::query()
                ->where('student_id', $student->getKey())
                ->where('semester_id', $semester->getKey())
                ->first();

            if ($reportCard === null
                || filled($reportCard->generation_error)
                || blank($reportCard->pdf_path)
                || ! Storage::disk('public')->exists((string) $reportCard->pdf_path)
            ) {
                $blockers[] = ['student' => $student->full_name, 'reason' => 'PDF rapor belum dibuat. Jalankan pembuatan massal terlebih dahulu.'];

                continue;
            }

            $cards[] = $reportCard;
        }

        if ($blockers !== []) {
            $listing = collect($blockers)
                ->take(5)
                ->map(fn (array $blocker): string => $blocker['student'].' - '.$blocker['reason'])
                ->implode('; ');

            throw ValidationException::withMessages([
                'report_card' => sprintf(
                    'Penerbitan dibatalkan. %d siswa belum siap: %s%s',
                    count($blockers),
                    $listing,
                    count($blockers) > 5 ? '; dan lainnya.' : '.',
                ),
            ]);
        }

        if ($cards === []) {
            throw ValidationException::withMessages([
                'report_card' => 'Tidak ada siswa aktif untuk diterbitkan rapornya.',
            ]);
        }

        DB::transaction(function () use ($cards, $semester): void {
            foreach ($cards as $reportCard) {
                if ($reportCard->published_at === null) {
                    $reportCard->forceFill(['published_at' => now()])->save();
                }
            }

            $semester->update(['report_published_at' => $semester->report_published_at ?? now()]);
        });

        AuditLogger::record('report_card.publish', $semester, [], [
            'published' => count($cards),
            'report_published_at' => $semester->report_published_at?->toIso8601String(),
        ]);

        return ['published' => count($cards)];
    }

    /**
     * Generation status overview for every active student in the year.
     *
     * Rows are sorted by name and carry a `revised` flag when the card was
     * regenerated after it was published (PRD: "Direvisi" on regenerate).
     *
     * @return array{
     *     summary: array{total: int, done: int, pending: int, failed: int, without_card: int},
     *     rows: list<array{student_id: int, full_name: string, nisn: string, status: string, error: string|null, generated_at: string|null, revised: bool}>
     * }
     */
    public function status(Semester $semester): array
    {
        $students = $this->students($semester)->sortBy('full_name')->values();

        $cards = ReportCard::query()
            ->where('semester_id', $semester->getKey())
            ->whereIn('student_id', $students->pluck('id')->all())
            ->get()
            ->keyBy('student_id');

        $summary = ['total' => $students->count(), 'done' => 0, 'pending' => 0, 'failed' => 0, 'without_card' => 0];
        $rows = [];

        foreach ($students as $student) {
            $card = $cards->get($student->getKey());

            if ($card === null) {
                $summary['without_card']++;
                $rows[] = [
                    'student_id' => $student->getKey(),
                    'full_name' => $student->full_name,
                    'nisn' => $student->nisn,
                    'status' => 'none',
                    'error' => null,
                    'generated_at' => null,
                    'revised' => false,
                ];

                continue;
            }

            if ($card->generation_status === GenerationStatus::Done) {
                $summary['done']++;
            } elseif ($card->generation_status === GenerationStatus::Failed) {
                $summary['failed']++;
            } else {
                $summary['pending']++;
            }

            $rows[] = [
                'student_id' => $student->getKey(),
                'full_name' => $student->full_name,
                'nisn' => $student->nisn,
                'status' => match (true) {
                    $card->generation_status === GenerationStatus::Done => GenerationStatus::Done->value,
                    $card->generation_status === GenerationStatus::Failed => GenerationStatus::Failed->value,
                    default => GenerationStatus::Pending->value,
                },
                'error' => $card->generation_error,
                'generated_at' => $card->generated_at?->format('d/m/Y H:i'),
                'revised' => $card->published_at !== null
                    && $card->generated_at !== null
                    && $card->generated_at->greaterThan($card->published_at),
            ];
        }

        return ['summary' => $summary, 'rows' => $rows];
    }

    /**
     * Active students enrolled in the semester's academic year.
     *
     * @return Collection<int, Student>
     */
    private function students(Semester $semester): Collection
    {
        return Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $semester->academic_year_id))
            ->get();
    }
}
