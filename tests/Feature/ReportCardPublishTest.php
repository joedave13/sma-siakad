<?php

use App\Enums\GenerationStatus;
use App\Enums\GradeComponent;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Jobs\GenerateReportCardPdf;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\GradeEntry;
use App\Models\ReportCard;
use App\Models\SchoolProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Grades\GradeEntryService;
use App\Services\ReportCard\PublishService;
use App\Services\ReportCard\ReportCardPdfService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function publishContext(): array
{
    Storage::fake('public');
    Storage::disk('public')->put('school/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    SchoolProfile::query()->create([
        'name' => 'SMA Negeri 1 Contoh',
        'address' => 'Jl. Pendidikan No. 1',
        'city' => 'Bandung',
        'headmaster_name' => 'Drs. Budi Santoso',
        'headmaster_nip' => '196801011992031001',
        'logo_path' => 'school/logo.png',
    ]);

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
    $admin = User::factory()->asRole(Role::Admin)->create();

    return [$year, $semester, $classroom, $teacher, $admin];
}

function completeStudentGrades(Semester $semester, Classroom $classroom, Teacher $teacher, Student $student, array $subjectNames = ['Matematika', 'Fisika']): void
{
    foreach ($subjectNames as $name) {
        $subject = Subject::query()->where('name', $name)->first() ?? Subject::factory()->create(['name' => $name, 'kkm' => 75]);
        $assignment = TeachingAssignment::query()
            ->where('semester_id', $semester->id)
            ->where('classroom_id', $classroom->id)
            ->where('subject_id', $subject->id)
            ->first() ?? TeachingAssignment::factory()->create([
                'semester_id' => $semester->id,
                'classroom_id' => $classroom->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
            ]);
        foreach ([GradeComponent::Assignment, GradeComponent::Quiz, GradeComponent::Midterm, GradeComponent::Final] as $component) {
            GradeEntry::factory()->create([
                'teaching_assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'component' => $component,
                'title' => $component->isSingular() ? null : $component->label().' 1',
                'score' => 85,
            ]);
        }
    }
}

it('locks a semester so teacher grade edits are rejected', function (): void {
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();
    $student = Student::factory()->create();
    ClassEnrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    completeStudentGrades($semester, $classroom, $teacher, $student);

    $assignment = TeachingAssignment::query()->first();
    $service = app(GradeEntryService::class);

    app(PublishService::class)->lock($semester, $admin);

    expect(fn () => $service->upsertEntry($assignment->fresh(), $student, GradeComponent::Midterm, null, '90'))
        ->toThrow(ValidationException::class, 'semester telah dikunci');
});

it('requires a reason to unlock and audit-logs it', function (): void {
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();
    $this->actingAs($admin);
    $service = app(PublishService::class);
    $service->lock($semester, $admin);

    expect(fn () => $service->unlock($semester, '   ', $admin))
        ->toThrow(ValidationException::class, 'Alasan pembukaan kunci wajib diisi.');

    $service->unlock($semester, 'Perbaikan nilai UTS kelas X-1', $admin);

    expect($semester->fresh()->grades_locked_at)->toBeNull()
        ->and(AuditLog::query()->where('action', 'semester.unlock')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'semester.unlock')->first()->new_values['reason'])->toBe('Perbaikan nilai UTS kelas X-1');
});

it('blocks publish when the school identity is incomplete', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();
    SchoolProfile::query()->delete();

    expect(fn () => app(PublishService::class)->publish($semester, $admin))
        ->toThrow(ValidationException::class, 'Pengaturan Sekolah');
});

it('blocks publish listing students with missing components', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();

    $ready = Student::factory()->create(['full_name' => 'Ahmad Fauzi']);
    $incomplete = Student::factory()->create(['full_name' => 'Budi Hartono']);
    foreach ([$ready, $incomplete] as $student) {
        ClassEnrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    }
    completeStudentGrades($semester, $classroom, $teacher, $ready, ['Informatika']);
    completeStudentGrades($semester, $classroom, $teacher, $incomplete, ['Informatika']);
    GradeEntry::query()
        ->where('student_id', $incomplete->id)
        ->where('component', GradeComponent::Quiz->value)
        ->update(['score' => null]);

    expect(fn () => app(PublishService::class)->publish($semester, $admin))
        ->toThrow(ValidationException::class, 'Budi Hartono — Nilai belum lengkap: Informatika (Quiz).');
});

it('bulk generation queues ready students and marks incomplete ones failed with the listing', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();

    $ready = Student::factory()->create();
    $incomplete = Student::factory()->create();
    foreach ([$ready, $incomplete] as $student) {
        ClassEnrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    }
    completeStudentGrades($semester, $classroom, $teacher, $ready);

    $result = app(PublishService::class)->generateBulk($semester);

    Bus::assertDispatched(GenerateReportCardPdf::class, 1);

    expect($result['queued'])->toBe(1)
        ->and($result['skipped'])->toHaveCount(1)
        ->and(ReportCard::query()->where('student_id', $ready->id)->first()->generation_status)->toBe(GenerationStatus::Pending)
        ->and(ReportCard::query()->where('student_id', $incomplete->id)->first()->generation_status)->toBe(GenerationStatus::Failed)
        ->and(ReportCard::query()->where('student_id', $incomplete->id)->first()->generation_error)->toContain('Nilai belum lengkap');
});

it('marks a queued generation done with a real pdf', function (): void {
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();
    $student = Student::factory()->create();
    ClassEnrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    completeStudentGrades($semester, $classroom, $teacher, $student);

    $result = app(PublishService::class)->generateBulk($semester);
    $card = ReportCard::query()->where('student_id', $student->id)->firstOrFail();

    (new GenerateReportCardPdf($card->id))->handle(app(ReportCardPdfService::class));

    $card->refresh();

    expect($result['queued'])->toBe(1)
        ->and($card->generation_status)->toBe(GenerationStatus::Done)
        ->and($card->generation_error)->toBeNull()
        ->and(substr(Storage::disk('public')->get((string) $card->pdf_path), 0, 4))->toBe('%PDF');
});

it('never overwrites a published pdf with a partial file when generation fails', function (): void {
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();
    $student = Student::factory()->create();
    ClassEnrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    completeStudentGrades($semester, $classroom, $teacher, $student);

    $card = app(ReportCardPdfService::class)->generate($student, $semester);
    $card->forceFill(['published_at' => now(), 'generation_status' => GenerationStatus::Done])->save();
    Storage::disk('public')->put($card->pdf_path, '%PDF-ORIGINAL-PUBLISHED');

    Storage::disk('public')->delete('school/logo.png');

    (new GenerateReportCardPdf($card->id))->handle(app(ReportCardPdfService::class));

    $card->refresh();

    expect($card->generation_status)->toBe(GenerationStatus::Failed)
        ->and($card->generation_error)->toContain('Pengaturan Sekolah')
        ->and(Storage::disk('public')->get((string) $card->pdf_path))->toBe('%PDF-ORIGINAL-PUBLISHED');
});

it('publishes every ready card, stamps published_at and audit-logs', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();
    $this->actingAs($admin);

    $student = Student::factory()->create();
    ClassEnrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    completeStudentGrades($semester, $classroom, $teacher, $student);

    app(PublishService::class)->generateBulk($semester);
    $card = ReportCard::query()->where('student_id', $student->id)->firstOrFail();
    (new GenerateReportCardPdf($card->id))->handle(app(ReportCardPdfService::class));

    $result = app(PublishService::class)->publish($semester, $admin);

    expect($result)->toBe(['published' => 1])
        ->and($card->refresh()->published_at)->not->toBeNull()
        ->and($semester->fresh()->report_published_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'report_card.publish')->count())->toBe(1);
});

it('retries failed generations back to pending and re-queues them', function (): void {
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();

    $incomplete = Student::factory()->create();
    ClassEnrollment::factory()->create(['student_id' => $incomplete->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    completeStudentGrades($semester, $classroom, $teacher, $incomplete, ['Informatika']);
    GradeEntry::query()
        ->where('student_id', $incomplete->id)
        ->where('component', GradeComponent::Quiz->value)
        ->update(['score' => null]);

    $result = app(PublishService::class)->generateBulk($semester);

    Bus::fake([GenerateReportCardPdf::class]);
    $retried = app(PublishService::class)->retryFailed($semester);

    expect($result['queued'])->toBe(0)
        ->and($retried)->toBe(1)
        ->and(ReportCard::query()->where('student_id', $incomplete->id)->first()->generation_status)->toBe(GenerationStatus::Pending)
        ->and(ReportCard::query()->where('student_id', $incomplete->id)->first()->generation_error)->toBeNull();

    Bus::assertDispatched(GenerateReportCardPdf::class, 1);
});

it('marks a regenerated published card Direvisi, keeps the previous version and uses the new identity', function (): void {
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();
    $student = Student::factory()->create();
    ClassEnrollment::factory()->create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);
    completeStudentGrades($semester, $classroom, $teacher, $student);

    $service = app(ReportCardPdfService::class);
    $card = $service->generate($student, $semester);
    $card->forceFill(['published_at' => now()])->save();
    Storage::disk('public')->put((string) $card->pdf_path, '%PDF-V1');
    $originalIdentity = $card->snapshot['school']['name'];

    SchoolProfile::query()->first()->update(['name' => 'SMA Negeri 2 Baru']);

    $card->refresh();
    expect($card->snapshot['school']['name'])->toBe($originalIdentity);

    $revised = $service->generate($student, $semester);

    expect($revised->snapshot['revision'])->toBe('Direvisi')
        ->and($revised->snapshot['school']['name'])->toBe('SMA Negeri 2 Baru')
        ->and(Storage::disk('public')->get((string) $revised->snapshot['previous_pdf_path']))->toBe('%PDF-V1')
        ->and(substr(Storage::disk('public')->get((string) $revised->pdf_path), 0, 4))->toBe('%PDF');
});

it('excludes non-active students from bulk generation and publish', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [$year, $semester, $classroom, $teacher, $admin] = publishContext();

    $transferred = Student::factory()->create(['status' => StudentStatus::Transferred]);
    ClassEnrollment::factory()->create(['student_id' => $transferred->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id]);

    $result = app(PublishService::class)->generateBulk($semester);

    expect($result['queued'])->toBe(0)
        ->and($result['skipped'])->toBe([])
        ->and(ReportCard::query()->count())->toBe(0);
});
