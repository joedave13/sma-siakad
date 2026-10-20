<?php

namespace App\Services\Promotion;

use App\Enums\PromotionBatchStatus;
use App\Enums\PromotionOutcome;
use App\Enums\StudentStatus;
use App\Models\AcademicSetting;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\PromotionBatch;
use App\Models\PromotionDecision;
use App\Models\Semester;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    /**
     * Propose one decision per active student in the from-year (preview only).
     *
     * The batch stays in `draft` until `apply()`; re-running updates existing
     * rows in place, so the preview is idempotent.
     *
     * @return array{batch: PromotionBatch, promoted: int, retained: int, graduated: int, unassigned: array<int, int>}
     *
     * @throws ValidationException
     */
    public function propose(AcademicYear $fromYear, AcademicYear $toYear): array
    {
        $this->assertNoAppliedBatch($fromYear, $toYear);

        $batch = PromotionBatch::query()->firstOrCreate([
            'from_academic_year_id' => $fromYear->getKey(),
            'to_academic_year_id' => $toYear->getKey(),
        ], [
            'status' => PromotionBatchStatus::Draft,
        ]);

        $maxFailed = AcademicSetting::getInt('promotion_max_failed_subjects', 3);

        $promoted = 0;
        $retained = 0;
        $graduated = 0;
        $unassigned = [];

        foreach ($this->studentsOf($fromYear) as $student) {
            $classroom = $student->classroomIn($fromYear->getKey());

            if ($classroom === null) {
                continue;
            }

            $failed = $this->failedSubjectCount($student, $fromYear);

            $outcome = $classroom->grade_level >= 12
                ? PromotionOutcome::Graduate
                : ($failed > $maxFailed ? PromotionOutcome::Retain : PromotionOutcome::Promote);

            $target = $this->targetClassroom($classroom, $toYear, $outcome);

            if ($target === null && $outcome !== PromotionOutcome::Graduate) {
                $unassigned[] = $student->getKey();
            }

            PromotionDecision::query()->updateOrCreate([
                'promotion_batch_id' => $batch->getKey(),
                'student_id' => $student->getKey(),
            ], [
                'from_classroom_id' => $classroom->getKey(),
                'to_classroom_id' => $target?->getKey(),
                'outcome' => $outcome,
            ]);

            match ($outcome) {
                PromotionOutcome::Promote => $promoted++,
                PromotionOutcome::Retain => $retained++,
                PromotionOutcome::Graduate => $graduated++,
            };
        }

        AuditLogger::record('promotion.propose', $batch, [], [
            'promoted' => $promoted,
            'retained' => $retained,
            'graduated' => $graduated,
            'unassigned' => $unassigned,
        ]);

        return [
            'batch' => $batch,
            'promoted' => $promoted,
            'retained' => $retained,
            'graduated' => $graduated,
            'unassigned' => $unassigned,
        ];
    }

    /**
     * Override a single student's proposed outcome with a required reason.
     *
     * @throws ValidationException
     */
    public function override(
        PromotionDecision $decision,
        PromotionOutcome $outcome,
        string $reason,
        ?int $targetClassroomId = null,
    ): PromotionDecision {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan perubahan keputusan wajib diisi.',
            ]);
        }

        if ($decision->promotionBatch->status !== PromotionBatchStatus::Draft) {
            throw ValidationException::withMessages([
                'promotion_batch' => 'Batch kenaikan kelas sudah diterapkan dan tidak dapat diubah.',
            ]);
        }

        $previous = [
            'outcome' => $decision->outcome->value,
            'to_classroom_id' => $decision->to_classroom_id,
            'is_overridden' => $decision->is_overridden,
        ];

        if ($outcome === PromotionOutcome::Graduate) {
            $target = null;
        } elseif ($targetClassroomId !== null) {
            $target = Classroom::query()->find($targetClassroomId);
        } elseif ($decision->fromClassroom !== null) {
            $target = $this->targetClassroom(
                $decision->fromClassroom,
                $decision->promotionBatch->toAcademicYear,
                $outcome,
            );
        } else {
            $target = null;
        }

        $decision->update([
            'outcome' => $outcome,
            'to_classroom_id' => $target?->getKey(),
            'is_overridden' => true,
            'override_reason' => $reason,
        ]);

        AuditLogger::record('promotion.override', $decision, $previous, [
            'outcome' => $outcome->value,
            'to_classroom_id' => $target?->getKey(),
            'override_reason' => $reason,
        ]);

        return $decision->refresh();
    }

    /**
     * Apply the draft batch: enroll every student into their target classroom.
     *
     * Idempotent - applying twice is a no-op. Blocked while any semester of
     * the from-year is unlocked or any non-graduate row has no target class.
     *
     * @throws ValidationException
     */
    public function apply(PromotionBatch $batch): void
    {
        if ($batch->status === PromotionBatchStatus::Applied) {
            return;
        }

        $this->assertBothYearsLocked($batch->fromAcademicYear);

        $unassigned = $batch->decisions()
            ->whereNull('to_classroom_id')
            ->where('outcome', '!=', PromotionOutcome::Graduate->value)
            ->pluck('student_id');

        if ($unassigned->isNotEmpty()) {
            throw ValidationException::withMessages([
                'promotion_batch' => 'Penerapan dibatalkan. Ada siswa yang belum memiliki kelas tujuan: '
                    .$unassigned->count().' siswa menunggu penetapan kelas.',
            ]);
        }

        DB::transaction(function () use ($batch): void {
            $batch->decisions()->with('student')->chunkById(200, function ($decisions) use ($batch): void {
                foreach ($decisions as $decision) {
                    $student = $decision->student;

                    if ($student === null) {
                        continue;
                    }

                    if ($decision->outcome === PromotionOutcome::Graduate) {
                        $student->update(['status' => StudentStatus::Graduated]);

                        continue;
                    }

                    ClassEnrollment::query()->updateOrCreate([
                        'student_id' => $student->getKey(),
                        'academic_year_id' => $batch->to_academic_year_id,
                    ], [
                        'classroom_id' => $decision->to_classroom_id,
                    ]);
                }
            });

            $batch->update([
                'status' => PromotionBatchStatus::Applied,
                'applied_by' => auth()->id(),
                'applied_at' => now(),
            ]);
        });

        AuditLogger::record('promotion.apply', $batch, ['status' => PromotionBatchStatus::Draft->value], [
            'status' => PromotionBatchStatus::Applied->value,
            'applied_at' => $batch->fresh()->applied_at?->toIso8601String(),
        ]);
    }

    /**
     * Subjects with a failing final score across the student's published report cards.
     */
    public function failedSubjectCount(Student $student, AcademicYear $year): int
    {
        $count = 0;

        $year->semesters()->get()
            ->filter(fn (Semester $semester): bool => $semester->report_published_at !== null)
            ->each(function (Semester $semester) use ($student, &$count): void {
                $reportCard = $student->reportCards()
                    ->where('semester_id', $semester->getKey())
                    ->first();

                if ($reportCard === null || $reportCard->snapshot === null) {
                    return;
                }

                foreach ($reportCard->snapshot['grades'] ?? [] as $grade) {
                    if (($grade['passed'] ?? true) === false) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * The default placement for a student moving into the to-year.
     *
     * Cohorts keep their number (X-1 -> XI-1) and, when the source class has
     * one, the track (IPA/IPS); grade 11 placement without a source track is
     * chosen by admin per student via `override()`.
     */
    public function targetClassroom(Classroom $fromClassroom, AcademicYear $toYear, PromotionOutcome $outcome): ?Classroom
    {
        if ($outcome === PromotionOutcome::Graduate) {
            return null;
        }

        $targetLevel = $outcome === PromotionOutcome::Retain
            ? $fromClassroom->grade_level
            : min($fromClassroom->grade_level + 1, 12);

        $candidates = Classroom::query()
            ->where('academic_year_id', $toYear->getKey())
            ->where('grade_level', $targetLevel)
            ->orderBy('name')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($fromClassroom->major !== null) {
            $withTrack = $candidates
                ->filter(fn (Classroom $classroom): bool => $classroom->major?->value === $fromClassroom->major->value)
                ->values();

            if ($withTrack->isNotEmpty()) {
                return $this->sameOrdinal($fromClassroom, $withTrack) ?? $withTrack->first();
            }
        }

        $sameNumber = $this->sameOrdinal($fromClassroom, $candidates);

        if ($sameNumber !== null) {
            return $sameNumber;
        }

        if ($fromClassroom->major === null) {
            $untracked = $candidates
                ->filter(fn (Classroom $classroom): bool => $classroom->major === null)
                ->values();

            if ($untracked->isNotEmpty()) {
                return $untracked->first();
            }
        }

        return $candidates->first();
    }

    /**
     * The candidate whose class number matches the source (X-1 -> XI-1), if any.
     *
     * @param  Collection<int, Classroom>  $candidates
     */
    private function sameOrdinal(Classroom $fromClassroom, Collection $candidates): ?Classroom
    {
        if (! preg_match('/(\d+)$/', $fromClassroom->name, $matches)) {
            return null;
        }

        return $candidates->first(
            fn (Classroom $classroom): bool => str_ends_with($classroom->name, '-'.$matches[1]),
        );
    }

    /**
     * @throws ValidationException
     */
    private function assertBothYearsLocked(AcademicYear $year): void
    {
        $unlocked = $year->semesters()
            ->whereNull('grades_locked_at')
            ->count();

        if ($unlocked > 0) {
            throw ValidationException::withMessages([
                'academic_year' => 'Kenaikan kelas hanya dapat dijalankan setelah semua semester terkunci.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertNoAppliedBatch(AcademicYear $fromYear, AcademicYear $toYear): void
    {
        $applied = PromotionBatch::query()
            ->where('from_academic_year_id', $fromYear->getKey())
            ->where('to_academic_year_id', $toYear->getKey())
            ->where('status', PromotionBatchStatus::Applied->value)
            ->exists();

        if ($applied) {
            throw ValidationException::withMessages([
                'promotion_batch' => 'Batch kenaikan kelas untuk pasangan tahun ajaran ini sudah pernah diterapkan.',
            ]);
        }
    }

    /**
     * @return Collection<int, Student>
     */
    private function studentsOf(AcademicYear $year): Collection
    {
        return Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $year->getKey()))
            ->get();
    }
}
