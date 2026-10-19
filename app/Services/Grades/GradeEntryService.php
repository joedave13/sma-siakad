<?php

namespace App\Services\Grades;

use App\Enums\GradeComponent;
use App\Enums\StudentStatus;
use App\Models\GradeEntry;
use App\Models\GradeRemark;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradeEntryService
{
    /**
     * Save one whole grade grid (scores and remarks) for one assignment in a
     * single transaction: every stale or invalid cell aborts the whole save.
     *
     * Blank cells without an existing entry are skipped unless another cell
     * in the same column carries a score, so a new column only persists once
     * at least one value is filled (blank is not zero).
     *
     * @param  array<int, array{student_id: int, component: GradeComponent|string, title?: string|null, score: string|null, expected_updated_at?: string|null}>  $entries
     * @param  array<int, array{student_id: int, remark: string|null}>  $remarks
     *
     * @throws ValidationException
     */
    public function saveGrid(TeachingAssignment $assignment, array $entries, array $remarks): void
    {
        $this->assertNotLocked($assignment, 'grades');

        $roster = $this->roster($assignment)->keyBy('id');
        $cellStudentIds = array_map(fn (array $cell): int => (int) $cell['student_id'], [...$entries, ...$remarks]);

        $names = [];
        foreach ($roster as $rosterStudent) {
            $names[$rosterStudent->id] = $rosterStudent->full_name;
        }

        foreach (array_unique($cellStudentIds) as $studentId) {
            if (! isset($names[$studentId])) {
                throw ValidationException::withMessages([
                    'grades' => 'Data siswa tidak valid.',
                ]);
            }
        }

        $activeColumns = [];
        foreach ($entries as $cell) {
            if (filled($cell['score'] ?? null)) {
                $activeColumns[$this->columnKey($cell)] = true;
            }
        }

        DB::transaction(function () use ($assignment, $entries, $remarks, $roster, $activeColumns, $names): void {
            foreach ($entries as $cell) {
                $component = $cell['component'] instanceof GradeComponent
                    ? $cell['component']
                    : GradeComponent::from((string) $cell['component']);
                $cellTitle = $cell['title'] ?? null;
                $title = $component->isSingular() ? null : ($cellTitle === '' ? null : $cellTitle);

                $existing = GradeEntry::query()
                    ->where('teaching_assignment_id', $assignment->getKey())
                    ->where('student_id', (int) $cell['student_id'])
                    ->where('component', $component->value)
                    ->when(
                        $component->isSingular(),
                        fn ($query) => $query->whereNull('title'),
                        fn ($query) => $query->where('title', $title),
                    )
                    ->first();

                if ($existing !== null) {
                    if ($existing->updated_at->toJSON() !== ($cell['expected_updated_at'] ?? null)) {
                        throw ValidationException::withMessages([
                            'grades' => sprintf(
                                'Nilai %s (%s) telah diubah oleh pengguna lain. Nilai saat ini: %s. Muat ulang halaman untuk melihat data terbaru.',
                                $names[(int) $cell['student_id']] ?? 'siswa',
                                $component->label(),
                                $existing->score === null ? 'kosong' : (string) $existing->score,
                            ),
                        ]);
                    }

                    $existing->score = $this->normalizeScore($cell['score'] ?? null, 'grades');
                    $existing->save();

                    continue;
                }

                if (! filled($cell['score'] ?? null) && ! isset($activeColumns[$this->columnKey($cell)])) {
                    continue;
                }

                $entry = new GradeEntry([
                    'teaching_assignment_id' => $assignment->getKey(),
                    'student_id' => (int) $cell['student_id'],
                    'component' => $component,
                    'title' => $title,
                ]);
                $entry->score = $this->normalizeScore($cell['score'] ?? null, 'grades');
                $entry->save();
            }

            foreach ($remarks as $remark) {
                $student = $roster->get((int) $remark['student_id']);

                if ($student === null) {
                    throw ValidationException::withMessages([
                        'grades' => 'Data siswa tidak valid.',
                    ]);
                }

                $this->saveRemark($assignment, $student, $remark['remark'] ?? null);
            }
        });
    }

    /**
     * Active students enrolled in the assignment's classroom for the assignment's year.
     *
     * @return Collection<int, Student>
     */
    public function roster(TeachingAssignment $assignment): Collection
    {
        return Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('classroom_id', $assignment->classroom_id)
                ->where('academic_year_id', $assignment->semester->academic_year_id))
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Create or update a grade entry for one component (and title, for repeatable components).
     *
     * @throws ValidationException
     */
    public function upsertEntry(
        TeachingAssignment $assignment,
        Student $student,
        GradeComponent $component,
        ?string $title,
        ?string $score,
    ): GradeEntry {
        $this->assertNotLocked($assignment);
        $numeric = $this->normalizeScore($score);

        $entry = GradeEntry::query()
            ->where('teaching_assignment_id', $assignment->getKey())
            ->where('student_id', $student->getKey())
            ->where('component', $component->value)
            ->when(
                $component->isSingular(),
                fn ($query) => $query->whereNull('title'),
                fn ($query) => $query->where('title', $title),
            )
            ->first() ?? new GradeEntry([
                'teaching_assignment_id' => $assignment->getKey(),
                'student_id' => $student->getKey(),
                'component' => $component,
                'title' => $component->isSingular() ? null : $title,
            ]);

        $entry->score = $numeric;
        $entry->save();

        return $entry;
    }

    /**
     * Update one entry's score with an optimistic check on updated_at.
     *
     * @throws ValidationException
     */
    public function updateScore(GradeEntry $entry, ?string $score, string $expectedUpdatedAt): GradeEntry
    {
        $this->assertNotLocked($entry->teachingAssignment);

        if ($entry->updated_at->toJSON() !== $expectedUpdatedAt) {
            throw ValidationException::withMessages([
                'score' => sprintf(
                    'Data nilai telah diubah oleh pengguna lain. Nilai saat ini: %s. Muat ulang halaman untuk melihat data terbaru.',
                    $entry->score === null ? 'kosong' : (string) $entry->score,
                ),
            ]);
        }

        $entry->score = $this->normalizeScore($score);
        $entry->save();

        return $entry;
    }

    /**
     * Upsert the per-subject remark for one student (unique pair).
     *
     * @throws ValidationException
     */
    public function saveRemark(TeachingAssignment $assignment, Student $student, ?string $remark): GradeRemark
    {
        $this->assertNotLocked($assignment);

        $row = GradeRemark::query()->firstOrNew([
            'teaching_assignment_id' => $assignment->getKey(),
            'student_id' => $student->getKey(),
        ]);
        $row->remark = $remark;
        $row->save();

        return $row;
    }

    /**
     * Blank is not zero: null or empty string stores as null.
     *
     * @throws ValidationException
     */
    private function normalizeScore(?string $score, string $errorKey = 'score'): ?float
    {
        if ($score === null || trim($score) === '') {
            return null;
        }

        if (! is_numeric($score)) {
            throw ValidationException::withMessages([
                $errorKey => 'Nilai harus berupa angka.',
            ]);
        }

        $value = (float) $score;

        if ($value < 0 || $value > 100) {
            throw ValidationException::withMessages([
                $errorKey => 'Nilai harus antara 0 dan 100.',
            ]);
        }

        if (str_contains($score, '.') && strlen(explode('.', $score)[1]) > 2) {
            throw ValidationException::withMessages([
                $errorKey => 'Nilai maksimal 2 angka desimal.',
            ]);
        }

        return $value;
    }

    /**
     * @param  array{component: GradeComponent|string, title?: string|null}  $cell
     */
    private function columnKey(array $cell): string
    {
        $component = $cell['component'] instanceof GradeComponent
            ? $cell['component']->value
            : (string) $cell['component'];

        return $component.'|'.($cell['title'] ?? '');
    }

    /**
     * @throws ValidationException
     */
    private function assertNotLocked(TeachingAssignment $assignment, string $errorKey = 'score'): void
    {
        if ($assignment->semester->isLocked()) {
            throw ValidationException::withMessages([
                $errorKey => 'Nilai tidak dapat diubah karena semester telah dikunci.',
            ]);
        }
    }
}
