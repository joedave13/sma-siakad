<?php

use App\Enums\GenerationStatus;
use App\Enums\GradeComponent;
use App\Enums\Role;
use App\Jobs\GenerateReportCardPdf;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\GradeEntry;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\SchoolProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function reportFlowContext(): array
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

function reportFlowCompleteGrades(Semester $semester, Classroom $classroom, Teacher $teacher, Student $student): void
{
    $subject = Subject::query()->where('name', 'Matematika')->first()
        ?? Subject::factory()->create(['name' => 'Matematika', 'kkm' => 75]);

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

function reportFlowCard(Student $student, Semester $semester, array $attributes = []): ReportCard
{
    $card = ReportCard::query()->create(array_merge([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'generation_status' => GenerationStatus::Done,
        'pdf_path' => 'report-cards/flow-'.$student->id.'-'.$semester->id.'.pdf',
    ], $attributes));

    if (filled($card->pdf_path)) {
        Storage::disk('public')->put((string) $card->pdf_path, '%PDF-1.4 rapor uji');
    }

    return $card;
}

function reportFlowEnroll(int $yearId, Classroom $classroom, Student ...$students): void
{
    foreach ($students as $student) {
        ClassEnrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $yearId,
        ]);
    }
}

it('redirects guests from report publish and download routes to login', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create();

    $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
    $this->get(route('admin.reports.show', $semester))->assertRedirect(route('login'));
    $this->post(route('admin.reports.lock', $semester))->assertRedirect(route('login'));
    $this->get(route('student.report-cards.download', $semester))->assertRedirect(route('login'));
    $this->get(route('guardian.report-cards.download', [$student, $semester]))->assertRedirect(route('login'));
});

it('forbids non admin users from the report publish pages', function (): void {
    $semester = Semester::factory()->create();

    foreach ([Role::Teacher, Role::Student, Role::Parent] as $role) {
        $this->actingAs(User::factory()->asRole($role)->create())
            ->get(route('admin.reports.index'))
            ->assertForbidden();
        $this->actingAs(User::factory()->asRole($role)->create())
            ->get(route('admin.reports.show', $semester))
            ->assertForbidden();
        $this->actingAs(User::factory()->asRole($role)->create())
            ->post(route('admin.reports.lock', $semester))
            ->assertForbidden();
    }
});

it('restricts report card downloads to the matching role', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create();

    $this->actingAs(User::factory()->asRole(Role::Teacher)->create())
        ->get(route('student.report-cards.download', $semester))
        ->assertForbidden();
    $this->actingAs(User::factory()->asRole(Role::Parent)->create())
        ->get(route('student.report-cards.download', $semester))
        ->assertForbidden();
    $this->actingAs(User::factory()->asRole(Role::Student)->create())
        ->get(route('guardian.report-cards.download', [$student, $semester]))
        ->assertForbidden();
});

it('exposes the publish report entry in the admin navigation', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.2.label', 'Rapor')
            ->where('nav.2.items.0.key', 'report-publish')
            ->where('nav.2.items.0.title', 'Terbitkan Rapor')
            ->where('nav.2.items.0.href', route('admin.reports.index'))
            ->where('nav.2.items.0.coming_soon', false)
        );
});

it('lists semesters with lock and publish status for the admin', function (): void {
    [, $semester] = reportFlowContext();

    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/Index')
            ->has('semesters', 1)
            ->where('semesters.0.id', $semester->id)
            ->where('semesters.0.name', 'Ganjil')
            ->where('semesters.0.academic_year', '2026/2027')
            ->where('semesters.0.locked', false)
            ->where('semesters.0.published', false)
        );
});

it('locks a semester from the index page and flashes the confirmation', function (): void {
    [, $semester] = reportFlowContext();

    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->post(route('admin.reports.lock', $semester))
        ->assertRedirect(route('admin.reports.index'));

    expect($semester->fresh()->grades_locked_at)->not->toBeNull()
        ->and(session('inertia.flash_data')['toast']['message'])->toBe('Nilai semester berhasil dikunci.');
});

it('requires a reason to unlock a semester through the ui', function (): void {
    [, $semester] = reportFlowContext();
    $admin = User::query()->where('role', Role::Admin)->firstOrFail();

    $this->actingAs($admin)->post(route('admin.reports.lock', $semester));

    $this->actingAs($admin)
        ->post(route('admin.reports.unlock', $semester), [])
        ->assertSessionHasErrors(['reason' => 'Alasan pembukaan kunci wajib diisi.']);

    expect($semester->fresh()->grades_locked_at)->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.reports.unlock', $semester), ['reason' => 'Perbaikan nilai UTS'])
        ->assertRedirect(route('admin.reports.index'));

    $audit = AuditLog::query()->where('action', 'semester.unlock')->first();

    expect($semester->fresh()->grades_locked_at)->toBeNull()
        ->and(session('inertia.flash_data')['toast']['message'])->toBe('Kunci nilai semester berhasil dibuka.')
        ->and($audit->new_values['reason'])->toBe('Perbaikan nilai UTS');
});

it('reports students as not generated before bulk generation', function (): void {
    [$year, $semester, $classroom, $teacher] = reportFlowContext();
    $adit = Student::factory()->create(['full_name' => 'Adit Wijaya']);
    $budi = Student::factory()->create(['full_name' => 'Budi Santoso']);
    reportFlowEnroll($year->id, $classroom, $adit, $budi);

    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->get(route('admin.reports.show', $semester))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/Show')
            ->where('semester.id', $semester->id)
            ->has('generation.rows', 2)
            ->where('generation.rows.0.full_name', 'Adit Wijaya')
            ->where('generation.rows.0.status', 'none')
            ->where('generation.rows.1.full_name', 'Budi Santoso')
            ->where('generation.rows.1.status', 'none')
            ->where('generation.summary.total', 2)
            ->where('generation.summary.without_card', 2)
            ->where('generation.summary.done', 0)
        );
});

it('queues ready students from the show page and reports pending and failed statuses', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [$year, $semester, $classroom, $teacher] = reportFlowContext();
    $adit = Student::factory()->create(['full_name' => 'Adit Wijaya']);
    $budi = Student::factory()->create(['full_name' => 'Budi Santoso']);
    reportFlowEnroll($year->id, $classroom, $adit, $budi);
    reportFlowCompleteGrades($semester, $classroom, $teacher, $adit);

    $admin = User::query()->where('role', Role::Admin)->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.reports.generate', $semester))
        ->assertRedirect(route('admin.reports.show', $semester));

    Bus::assertDispatched(GenerateReportCardPdf::class, 1);

    expect(session('inertia.flash_data')['toast']['message'])
        ->toBe('Pembuatan PDF dijalankan untuk 1 siswa. 1 siswa dilewati karena nilai belum lengkap.');

    $this->actingAs($admin)
        ->get(route('admin.reports.show', $semester))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('generation.rows.0.status', 'pending')
            ->where('generation.rows.1.status', 'failed')
            ->where(
                'generation.rows.1.error',
                'Nilai belum lengkap: Matematika (Tugas), Matematika (Quiz), Matematika (UTS), Matematika (UAS)',
            )
            ->where('generation.summary.pending', 1)
            ->where('generation.summary.failed', 1)
        );
});

it('explains bulk generation when every student is skipped', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [$year, $semester, $classroom, $teacher] = reportFlowContext();
    reportFlowCompleteGrades($semester, $classroom, $teacher, Student::factory()->create());
    reportFlowEnroll($year->id, $classroom, Student::factory()->create(['full_name' => 'Budi Santoso']));

    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->post(route('admin.reports.generate', $semester))
        ->assertRedirect(route('admin.reports.show', $semester));

    Bus::assertNotDispatched(GenerateReportCardPdf::class);

    expect(session('inertia.flash_data')['report_card'])
        ->toBe(['PDF tidak dibuat karena nilai belum lengkap pada semua siswa.']);
});

it('explains bulk generation when no students are enrolled', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [, $semester] = reportFlowContext();

    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->post(route('admin.reports.generate', $semester))
        ->assertRedirect(route('admin.reports.show', $semester));

    Bus::assertNotDispatched(GenerateReportCardPdf::class);

    expect(session('inertia.flash_data')['report_card'])
        ->toBe(['Tidak ada siswa aktif untuk membuat PDF.']);
});

it('flags cards as revised when they were regenerated after publishing', function (): void {
    [$year, $semester, $classroom, $teacher] = reportFlowContext();
    $adit = Student::factory()->create(['full_name' => 'Adit Wijaya']);
    $budi = Student::factory()->create(['full_name' => 'Budi Santoso']);
    reportFlowEnroll($year->id, $classroom, $adit, $budi);

    reportFlowCard($adit, $semester, [
        'published_at' => now()->subHour(),
        'generated_at' => now(),
    ]);
    reportFlowCard($budi, $semester);

    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->get(route('admin.reports.show', $semester))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('generation.rows.0.revised', true)
            ->where('generation.rows.1.revised', false)
        );
});

it('blocks publishing from the show page with the blocker listing', function (): void {
    [$year, $semester, $classroom] = reportFlowContext();
    reportFlowEnroll($year->id, $classroom, Student::factory()->create(['full_name' => 'Budi Santoso']));

    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->post(route('admin.reports.publish', $semester))
        ->assertSessionHasErrors('report_card');

    expect(session('errors')->first('report_card'))
        ->toContain('Penerbitan dibatalkan.')
        ->and($semester->fresh()->report_published_at)->toBeNull();
});

it('publishes from the show page and flashes the published count', function (): void {
    [$year, $semester, $classroom, $teacher] = reportFlowContext();
    $adit = Student::factory()->create(['full_name' => 'Adit Wijaya']);
    reportFlowEnroll($year->id, $classroom, $adit);
    reportFlowCompleteGrades($semester, $classroom, $teacher, $adit);
    reportFlowCard($adit, $semester);

    $admin = User::query()->where('role', Role::Admin)->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.reports.publish', $semester))
        ->assertRedirect(route('admin.reports.show', $semester));

    expect(session('inertia.flash_data')['toast']['message'])
        ->toBe('Rapor berhasil diterbitkan untuk 1 siswa.')
        ->and($semester->fresh()->report_published_at)->not->toBeNull();

    $publishedAt = $semester->fresh()->report_published_at->format('d/m/Y H:i');

    $this->actingAs($admin)
        ->get(route('admin.reports.show', $semester))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('semester.published', true)
            ->where('semester.published_at', $publishedAt)
        );
});

it('retries failed pdf generations from the show page', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [, $semester] = reportFlowContext();
    $adit = Student::factory()->create(['full_name' => 'Adit Wijaya']);
    reportFlowCard($adit, $semester, [
        'generation_status' => GenerationStatus::Failed,
        'generation_error' => 'Nilai belum lengkap: Matematika (Quiz).',
        'pdf_path' => null,
    ]);

    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->post(route('admin.reports.retry', $semester))
        ->assertRedirect(route('admin.reports.show', $semester));

    Bus::assertDispatched(GenerateReportCardPdf::class, 1);

    expect(session('inertia.flash_data')['toast']['message'])
        ->toBe('Percobaan ulang dijalankan untuk 1 PDF yang gagal.')
        ->and(ReportCard::query()->firstOrFail()->generation_status)->toBe(GenerationStatus::Pending);
});

it('shows an error when there is no failed pdf to retry', function (): void {
    Bus::fake([GenerateReportCardPdf::class]);
    [, $semester] = reportFlowContext();

    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->post(route('admin.reports.retry', $semester))
        ->assertRedirect(route('admin.reports.show', $semester));

    Bus::assertNotDispatched(GenerateReportCardPdf::class);

    expect(session('inertia.flash_data')['report_card'])
        ->toBe(['Tidak ada PDF gagal untuk diulangi.']);
});

it('lets a student download their own published report card pdf', function (): void {
    [, $semester] = reportFlowContext();
    $student = Student::factory()->create();
    reportFlowCard($student, $semester, ['published_at' => now()]);

    $this->actingAs($student->user)
        ->get(route('student.report-cards.download', $semester))
        ->assertOk()
        ->assertDownload('rapor-'.$student->nisn.'-ganjil.pdf');
});

it('blocks the student download until the report card is published', function (): void {
    [, $semester] = reportFlowContext();
    $student = Student::factory()->create();
    reportFlowCard($student, $semester);

    $this->actingAs($student->user)
        ->get(route('student.report-cards.download', $semester))
        ->assertForbidden();
});

it('returns not found when the report card or its pdf file is missing', function (): void {
    [, $semester] = reportFlowContext();
    $student = Student::factory()->create();

    $this->actingAs($student->user)
        ->get(route('student.report-cards.download', $semester))
        ->assertNotFound();

    $card = reportFlowCard($student, $semester, ['published_at' => now()]);
    Storage::disk('public')->delete((string) $card->pdf_path);

    $this->actingAs($student->user)
        ->get(route('student.report-cards.download', $semester))
        ->assertNotFound();
});

it('lets a guardian download a linked childs published report card pdf', function (): void {
    [, $semester] = reportFlowContext();
    $student = Student::factory()->create();
    $guardian = Guardian::factory()->create();
    $guardian->students()->attach($student->id, ['relationship' => 'Ibu']);
    reportFlowCard($student, $semester, ['published_at' => now()]);

    $this->actingAs($guardian->user)
        ->get(route('guardian.report-cards.download', [$student, $semester]))
        ->assertOk()
        ->assertDownload('rapor-'.$student->nisn.'-ganjil.pdf');
});

it('blocks guardians from students they are not linked to', function (): void {
    [, $semester] = reportFlowContext();
    $linked = Student::factory()->create();
    $other = Student::factory()->create();
    $guardian = Guardian::factory()->create();
    $guardian->students()->attach($linked->id, ['relationship' => 'Ayah']);
    reportFlowCard($other, $semester, ['published_at' => now()]);

    $this->actingAs($guardian->user)
        ->get(route('guardian.report-cards.download', [$other, $semester]))
        ->assertForbidden();
});
