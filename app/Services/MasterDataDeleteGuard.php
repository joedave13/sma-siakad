<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\GradeEntry;
use App\Models\LessonPeriod;
use App\Models\ReportCard;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MasterDataDeleteGuard
{
    /**
     * Block a soft delete while dependent records still reference the model,
     * listing every blocker with an Indonesian explanation.
     *
     * @throws ValidationException
     */
    public function blockIfInUse(Model $model): void
    {
        $blockers = $this->blockers($model)
            ->filter(fn (int $count): bool => $count > 0);

        if ($blockers->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'delete' => 'Data tidak dapat dihapus karena masih digunakan oleh: '.$blockers
                ->map(fn (int $count, string $label): string => "{$count} {$label}")
                ->implode(', ').'.',
        ]);
    }

    /**
     * Dependent counts keyed by Indonesian labels. Structural children are
     * counted without trashed rows; hard history rows (nilai, sesi presensi)
     * are counted even through trashed intermediate records so dependent
     * grades and attendance always block deletion.
     *
     * @return Collection<string, int<0, max>>
     */
    private function blockers(Model $model): Collection
    {
        return collect(match (true) {
            $model instanceof AcademicYear => [
                'semester' => $model->semesters()->count(),
                'ruang kelas' => Classroom::query()->where('academic_year_id', $model->getKey())->count(),
                'peserta didik terdaftar' => ClassEnrollment::query()->where('academic_year_id', $model->getKey())->count(),
            ],
            $model instanceof Semester => [
                'penugasan mengajar' => $model->teachingAssignments()->count(),
                'rapor' => ReportCard::query()->where('semester_id', $model->getKey())->count(),
                'nilai' => GradeEntry::query()
                    ->whereHas('teachingAssignment', fn ($query) => $query
                        ->withTrashed()
                        ->where('semester_id', $model->getKey()))
                    ->count(),
                'sesi presensi' => AttendanceSession::query()
                    ->whereHas('schedule', fn ($query) => $query
                        ->withTrashed()
                        ->whereHas('teachingAssignment', fn ($assignment) => $assignment
                            ->withTrashed()
                            ->where('semester_id', $model->getKey())))
                    ->count(),
            ],
            $model instanceof LessonPeriod => [
                'jadwal' => Schedule::query()->where('lesson_period_id', $model->getKey())->count(),
                'sesi presensi' => AttendanceSession::query()
                    ->whereHas('schedule', fn ($query) => $query
                        ->withTrashed()
                        ->where('lesson_period_id', $model->getKey()))
                    ->count(),
            ],
            $model instanceof Subject => [
                'penugasan mengajar' => TeachingAssignment::query()->where('subject_id', $model->getKey())->count(),
                'nilai' => GradeEntry::query()
                    ->whereHas('teachingAssignment', fn ($query) => $query
                        ->withTrashed()
                        ->where('subject_id', $model->getKey()))
                    ->count(),
            ],
            $model instanceof Classroom => [
                'peserta didik terdaftar' => ClassEnrollment::query()->where('classroom_id', $model->getKey())->count(),
                'penugasan mengajar' => TeachingAssignment::query()->where('classroom_id', $model->getKey())->count(),
                'nilai' => GradeEntry::query()
                    ->whereHas('teachingAssignment', fn ($query) => $query
                        ->withTrashed()
                        ->where('classroom_id', $model->getKey()))
                    ->count(),
            ],
            default => [],
        });
    }
}
