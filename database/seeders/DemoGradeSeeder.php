<?php

namespace Database\Seeders;

use App\Enums\GradeComponent;
use App\Enums\StudentStatus;
use App\Models\ClassEnrollment;
use App\Models\GradeEntry;
use App\Models\GradeRemark;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TeachingAssignment;
use Illuminate\Database\Seeder;

class DemoGradeSeeder extends Seeder
{
    /**
     * Seed every component score for every assignment and student, leaving
     * the two designated grading gaps without a Final entry, plus remarks.
     */
    public function run(): void
    {
        $semester = Semester::query()->where('is_active', true)->firstOrFail();
        $ordinals = DemoData::ordinalsByNisn();
        $gaps = $this->gapKeys($semester);

        $rosters = [];
        foreach (ClassEnrollment::query()
            ->where('academic_year_id', $semester->academic_year_id)
            ->whereHas('student', fn ($query) => $query->where('status', StudentStatus::Active->value))
            ->with('student')
            ->get() as $enrollment) {
            $student = $enrollment->student;

            if ($student !== null) {
                $rosters[$enrollment->classroom_id][] = $student;
            }
        }

        $assignments = TeachingAssignment::query()
            ->where('semester_id', $semester->getKey())
            ->with('subject')
            ->get();

        foreach ($assignments as $assignment) {
            $students = $rosters[$assignment->classroom_id] ?? [];

            foreach ($students as $student) {
                $ordinal = $ordinals[$student->nisn] ?? null;

                if ($ordinal === null) {
                    continue;
                }

                $this->storeEntries($assignment->getKey(), $assignment->subject->code, $student, $ordinal, $gaps);
                $this->storeRemark($assignment, $student, $ordinal);
            }
        }
    }

    /**
     * The assignment|student keys missing their Final component.
     *
     * @return array<string, true>
     */
    private function gapKeys(Semester $semester): array
    {
        $keys = [];

        foreach (DemoData::GRADE_GAPS as $gap) {
            $assignment = TeachingAssignment::query()
                ->where('semester_id', $semester->getKey())
                ->whereHas('classroom', fn ($query) => $query
                    ->where('academic_year_id', $semester->academic_year_id)
                    ->where('name', $gap['classroom']))
                ->whereHas('subject', fn ($query) => $query->where('code', $gap['subject']))
                ->first();

            $student = Student::query()->where('nisn', DemoData::nisnFor($gap['student']))->first();

            if ($assignment === null || $student === null) {
                continue;
            }

            $keys[$assignment->getKey().'|'.$student->getKey()] = true;
        }

        return $keys;
    }

    /**
     * Store the six component entries for one assignment and student.
     *
     * @param  array<string, true>  $gaps
     */
    private function storeEntries(int $assignmentId, string $subjectCode, Student $student, int $ordinal, array $gaps): void
    {
        $gapKey = $assignmentId.'|'.$student->getKey();
        $subjectIndex = DemoData::subjectIndexFor($subjectCode);
        $ability = DemoData::STUDENTS[$ordinal]['ability'];

        $components = [
            [GradeComponent::Assignment, 'Tugas 1'],
            [GradeComponent::Assignment, 'Tugas 2'],
            [GradeComponent::Quiz, 'Quiz 1'],
            [GradeComponent::Quiz, 'Quiz 2'],
            [GradeComponent::Midterm, 'UTS'],
            [GradeComponent::Final, 'UAS'],
        ];

        foreach ($components as $entryIndex => [$component, $title]) {
            if ($component === GradeComponent::Final && isset($gaps[$gapKey])) {
                continue;
            }

            GradeEntry::query()->firstOrCreate(
                [
                    'teaching_assignment_id' => $assignmentId,
                    'student_id' => $student->getKey(),
                    'component' => $component,
                    'title' => $title,
                ],
                ['score' => DemoData::scoreFor($ability, $ordinal, $subjectIndex, $entryIndex)],
            );
        }
    }

    /**
     * Store the seeded subject remark for about a third of grade rows.
     */
    private function storeRemark(TeachingAssignment $assignment, Student $student, int $ordinal): void
    {
        $remark = DemoData::remarkFor($ordinal, DemoData::subjectIndexFor($assignment->subject->code));

        if ($remark === null) {
            return;
        }

        GradeRemark::query()->firstOrCreate(
            [
                'teaching_assignment_id' => $assignment->getKey(),
                'student_id' => $student->getKey(),
            ],
            ['remark' => $remark],
        );
    }
}
