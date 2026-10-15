<?php

use App\Enums\AttendanceStatus;
use App\Enums\GradeComponent;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\GradeEntry;
use App\Models\SchoolProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Services\ReportCard\ReportCardPdfService;
use App\Services\ReportCard\ReportCardSnapshotBuilder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function reportCardContext(): array
{
    Storage::fake('public');

    $year = AcademicYear::factory()->create(['name' => '2026/2027']);
    $semester = Semester::factory()->create([
        'academic_year_id' => $year->id,
        'name' => 'ganjil',
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
    ]);
    $teacher = Teacher::factory()->create();
    $classroom = Classroom::factory()->create([
        'academic_year_id' => $year->id,
        'grade_level' => 10,
        'major' => null,
        'homeroom_teacher_id' => $teacher->id,
    ]);
    $student = Student::factory()->create();
    ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    return [$year, $semester, $classroom, $student, $teacher];
}

function completeSchoolProfile(): SchoolProfile
{
    Storage::disk('public')->put('school/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    return SchoolProfile::query()->create([
        'name' => 'SMA Negeri 1 Contoh',
        'npsn' => '20123456',
        'address' => 'Jl. Pendidikan No. 1',
        'city' => 'Bandung',
        'headmaster_name' => 'Drs. Budi Santoso',
        'headmaster_nip' => '196801011992031001',
        'logo_path' => 'school/logo.png',
    ]);
}

it('builds a snapshot with grades, predicates and attendance counts', function (): void {
    [$year, $semester, $classroom, $student, $teacher] = reportCardContext();
    completeSchoolProfile();

    $subject = Subject::factory()->create(['kkm' => 75]);
    $assignment = TeachingAssignment::factory()->create([
        'semester_id' => $semester->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
    ]);

    GradeEntry::factory()->create(['teaching_assignment_id' => $assignment->id, 'student_id' => $student->id, 'component' => GradeComponent::Assignment, 'title' => 'Tugas 1', 'score' => 90]);
    GradeEntry::factory()->create(['teaching_assignment_id' => $assignment->id, 'student_id' => $student->id, 'component' => GradeComponent::Quiz, 'title' => 'Kuis 1', 'score' => 85]);
    GradeEntry::factory()->create(['teaching_assignment_id' => $assignment->id, 'student_id' => $student->id, 'component' => GradeComponent::Midterm, 'title' => null, 'score' => 80]);
    GradeEntry::factory()->create(['teaching_assignment_id' => $assignment->id, 'student_id' => $student->id, 'component' => GradeComponent::Final, 'title' => null, 'score' => 78]);

    DailyAttendance::query()->create(['student_id' => $student->id, 'date' => '2026-07-06', 'status' => AttendanceStatus::Present->value, 'computed_at' => now()]);
    DailyAttendance::query()->create(['student_id' => $student->id, 'date' => '2026-07-07', 'status' => AttendanceStatus::Sick->value, 'computed_at' => now()]);
    DailyAttendance::query()->create(['student_id' => $student->id, 'date' => '2026-07-08', 'status' => AttendanceStatus::Present->value, 'is_late' => true, 'computed_at' => now()]);
    DailyAttendance::query()->create(['student_id' => $student->id, 'date' => '2026-01-05', 'status' => AttendanceStatus::Absent->value, 'computed_at' => now()]);

    $snapshot = app(ReportCardSnapshotBuilder::class)->build($student, $semester);

    // 90*.3 + 85*.2 + 80*.2 + 78*.3 = 27+17+16+23.4 = 83.4 => 83
    expect($snapshot['grades'][0]['score'])->toBe(83)
        ->and($snapshot['grades'][0]['predicate'])->toBe('B')
        ->and($snapshot['grades'][0]['passed'])->toBeTrue()
        ->and($snapshot['attendance'])->toBe([
            'sick' => 1,
            'permitted' => 0,
            'absent' => 0, // out of semester range
            'late' => 1,
            'school_days' => 3,
            'present_days' => 2,
            'percentage' => 67,
        ])
        ->and($snapshot['school']['name'])->toBe('SMA Negeri 1 Contoh')
        ->and($snapshot['student']['classroom_name'])->toBe($classroom->name)
        ->and($snapshot['homeroom_teacher'])->toBe($teacher->full_name);
});

it('generates a real PDF and stores the snapshot on the report card row', function (): void {
    [$year, $semester, $classroom, $student] = reportCardContext();
    completeSchoolProfile();

    $reportCard = app(ReportCardPdfService::class)->generate($student, $semester);

    $contents = Storage::disk('public')->get($reportCard->pdf_path);

    expect($reportCard->pdf_path)->not->toBeNull()
        ->and($reportCard->snapshot['school']['name'])->toBe('SMA Negeri 1 Contoh')
        ->and($reportCard->generated_at)->not->toBeNull()
        ->and($reportCard->isPublished())->toBeFalse()
        ->and(substr($contents, 0, 4))->toBe('%PDF')
        ->and(strlen($contents))->toBeGreaterThan(2000);
});

it('blocks generation when the school identity is incomplete', function (): void {
    Storage::fake('public');
    [$year, $semester, $classroom, $student] = reportCardContext();

    expect(fn () => app(ReportCardPdfService::class)->generate($student, $semester))
        ->toThrow(ValidationException::class, 'Identitas sekolah belum lengkap atau file logo tidak ditemukan. Lengkapi data di halaman Pengaturan Sekolah sebelum membuat rapor.');
});

it('blocks generation when the logo file is missing on disk', function (): void {
    [$year, $semester, $classroom, $student] = reportCardContext();
    $profile = completeSchoolProfile();
    Storage::disk('public')->delete($profile->logo_path);

    expect(fn () => app(ReportCardPdfService::class)->generate($student, $semester))
        ->toThrow(ValidationException::class, 'Pengaturan Sekolah');
});

it('generates without a headmaster signature, leaving the signature block blank', function (): void {
    [$year, $semester, $classroom, $student] = reportCardContext();
    completeSchoolProfile();

    $reportCard = app(ReportCardPdfService::class)->generate($student, $semester);

    expect($reportCard->snapshot['school']['signature_path'])->toBeNull()
        ->and(substr(Storage::disk('public')->get($reportCard->pdf_path), 0, 4))->toBe('%PDF');
});

it('resolves predicate bands A B C D from the settings', function (): void {
    reportCardContext();
    completeSchoolProfile();
    $builder = app(ReportCardSnapshotBuilder::class);

    expect($builder->predicateFor(90, 75))->toBe('A')
        ->and($builder->predicateFor(89.5, 75))->toBe('B')
        ->and($builder->predicateFor(80, 75))->toBe('B')
        ->and($builder->predicateFor(79, 75))->toBe('C')
        ->and($builder->predicateFor(75, 75))->toBe('C')
        ->and($builder->predicateFor(74, 75))->toBe('D')
        ->and($builder->predicateFor(85, 88))->toBe('B');
});
