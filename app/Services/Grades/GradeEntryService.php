<?php

namespace App\Services\Grades;

use App\Enums\GradeComponent;
use App\Models\GradeEntry;
use App\Models\GradeRemark;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Illuminate\Validation\ValidationException;

class GradeEntryService
{
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
    private function normalizeScore(?string $score): ?float
    {
        if ($score === null || trim($score) === '') {
            return null;
        }

        if (! is_numeric($score)) {
            throw ValidationException::withMessages([
                'score' => 'Nilai harus berupa angka.',
            ]);
        }

        $value = (float) $score;

        if ($value < 0 || $value > 100) {
            throw ValidationException::withMessages([
                'score' => 'Nilai harus antara 0 dan 100.',
            ]);
        }

        if (str_contains($score, '.') && strlen(explode('.', $score)[1]) > 2) {
            throw ValidationException::withMessages([
                'score' => 'Nilai maksimal 2 angka desimal.',
            ]);
        }

        return $value;
    }

    /**
     * @throws ValidationException
     */
    private function assertNotLocked(TeachingAssignment $assignment): void
    {
        if ($assignment->semester->isLocked()) {
            throw ValidationException::withMessages([
                'score' => 'Nilai tidak dapat diubah karena semester telah dikunci.',
            ]);
        }
    }
}
