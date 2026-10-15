<?php

namespace App\Services\ReportCard;

use App\Models\AcademicSetting;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\GradeEntry;
use App\Models\SchoolProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Grades\FinalScoreCalculator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportCardSnapshotBuilder
{
    public function __construct(private FinalScoreCalculator $calculator) {}

    /**
     * Build the immutable snapshot stored on the report card at generate time.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function build(Student $student, Semester $semester): array
    {
        $profile = $this->assertUsableIdentity();

        $classroom = $this->classroomFor($student, $semester);

        $grades = $this->grades($student, $semester, $classroom?->id);
        $scored = array_filter($grades, fn (array $grade): bool => $grade['score'] !== null);

        return [
            'school' => [
                'name' => $profile->name,
                'npsn' => $profile->npsn,
                'address' => $profile->address,
                'city' => $profile->city,
                'phone' => $profile->phone,
                'email' => $profile->email,
                'headmaster_name' => $profile->headmaster_name,
                'headmaster_nip' => $profile->headmaster_nip,
                'logo_path' => $profile->logo_path,
                'signature_path' => $profile->headmaster_signature_path,
            ],
            'student' => [
                'full_name' => $student->full_name,
                'nisn' => $student->nisn,
                'nis' => $student->nis,
                'classroom_name' => $classroom?->name,
                'grade_level' => $classroom?->grade_level,
            ],
            'semester' => [
                'name' => $semester->name->label(),
                'academic_year' => $semester->academicYear->name,
            ],
            'homeroom_teacher' => $classroom?->homeroomTeacher?->full_name,
            'grades' => $grades,
            'score_summary' => [
                'total' => array_sum(array_column($scored, 'score')),
                'average' => $scored === [] ? null : round(array_sum(array_column($scored, 'score')) / count($scored), 2),
            ],
            'attendance' => $this->attendance($student, $semester),
            'homeroom_note' => null,
            'promotion_outcome' => null,
            'generated_at' => now('Asia/Jakarta')->toISOString(),
        ];
    }

    /**
     * The "Subject: Component" labels still missing a score for this student this semester.
     *
     * @return array<int, string>
     */
    public function missingComponentsFor(Student $student, Semester $semester): array
    {
        $classroomId = $this->classroomFor($student, $semester)?->getKey();

        if ($classroomId === null) {
            return [];
        }

        $assignments = $semester->teachingAssignments()
            ->where('classroom_id', $classroomId)
            ->with('subject')
            ->get();

        $missing = [];
        foreach ($assignments as $assignment) {
            $entries = GradeEntry::query()
                ->where('teaching_assignment_id', $assignment->getKey())
                ->where('student_id', $student->getKey())
                ->get()
                ->map(fn (GradeEntry $entry): array => [
                    'component' => $entry->component,
                    'score' => $entry->score,
                ])
                ->all();

            foreach ($this->calculator->missingComponents($entries) as $component) {
                $missing[] = $assignment->subject->name.' ('.$component->label().')';
            }
        }

        return $missing;
    }

    /**
     * The Indonesian predicate letter for one score (A >= 90, B 80-89, C from KKM to 79, D < KKM).
     */
    public function predicateFor(float $score, int $kkm): string
    {
        $aMin = AcademicSetting::getInt('predicate_a_min', 90);
        $bMin = AcademicSetting::getInt('predicate_b_min', 80);
        $cMin = AcademicSetting::getInt('predicate_c_min', 75);

        if ($score >= $aMin) {
            return 'A';
        }
        if ($score >= $bMin) {
            return 'B';
        }
        if ($score >= max($kkm, $cMin)) {
            return 'C';
        }

        return 'D';
    }

    /**
     * Per-subject final scores with predicate, KKM pass flag and remark.
     *
     * @return array<int, array{subject: string, score: ?int, kkm: int, predicate: string, passed: bool, remark: ?string}>
     */
    private function grades(Student $student, Semester $semester, ?int $classroomId): array
    {
        if ($classroomId === null) {
            return [];
        }

        $assignments = $semester->teachingAssignments()
            ->where('classroom_id', $classroomId)
            ->with('subject')
            ->get();

        $rows = [];
        foreach ($assignments as $assignment) {
            $entries = GradeEntry::query()
                ->where('teaching_assignment_id', $assignment->getKey())
                ->where('student_id', $student->getKey())
                ->get()
                ->map(fn (GradeEntry $entry): array => [
                    'component' => $entry->component,
                    'score' => $entry->score,
                ])
                ->all();

            $score = $this->calculator->finalScore($entries);
            $kkm = $assignment->subject->kkm;

            $rows[] = [
                'subject' => $assignment->subject->name,
                'score' => $score,
                'kkm' => $kkm,
                'predicate' => $score === null ? '-' : $this->predicateFor($score, $kkm),
                'passed' => $score === null ? false : $score >= $kkm,
                'remark' => $assignment->gradeRemarks()
                    ->where('student_id', $student->getKey())
                    ->value('remark'),
            ];
        }

        return $rows;
    }

    /**
     * Daily attendance counts over the semester's date range.
     *
     * @return array{sick: int, permitted: int, absent: int, late: int, school_days: int, present_days: int, percentage: int}
     */
    private function attendance(Student $student, Semester $semester): array
    {
        $days = DailyAttendance::query()
            ->where('student_id', $student->getKey())
            ->when(
                $semester->start_date !== null && $semester->end_date !== null,
                fn ($query) => $query
                    ->whereBetween('date', [$semester->start_date->toDateString(), $semester->end_date->toDateString()]),
            )
            ->get();

        $schoolDays = $days->count();
        $presentDays = $days->where('status.value', 'present')->count();

        return [
            'sick' => $days->where('status.value', 'sick')->count(),
            'permitted' => $days->where('status.value', 'permitted')->count(),
            'absent' => $days->where('status.value', 'absent')->count(),
            'late' => $days->where('is_late', true)->count(),
            'school_days' => $schoolDays,
            'present_days' => $presentDays,
            'percentage' => $schoolDays === 0 ? 100 : (int) round($presentDays / $schoolDays * 100),
        ];
    }

    private function classroomFor(Student $student, Semester $semester): ?Classroom
    {
        return $student->enrollments()
            ->where('academic_year_id', $semester->academic_year_id)
            ->with('classroom')
            ->first()
            ?->classroom;
    }

    /**
     * @throws ValidationException
     */
    public function assertUsableIdentity(): SchoolProfile
    {
        $profile = SchoolProfile::current();

        $required = ['name', 'address', 'city', 'headmaster_name', 'headmaster_nip'];
        $incomplete = $profile === null
            || collect($required)->contains(fn (string $field): bool => blank($profile->{$field}))
            || blank($profile->logo_path)
            || ! Storage::disk('public')->exists((string) $profile->logo_path);

        if ($incomplete) {
            throw ValidationException::withMessages([
                'report_card' => 'Identitas sekolah belum lengkap atau file logo tidak ditemukan. Lengkapi data di halaman Pengaturan Sekolah sebelum membuat rapor.',
            ]);
        }

        return $profile;
    }
}
