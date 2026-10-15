<?php

use App\Enums\ClassMajor;
use App\Enums\PromotionBatchStatus;
use App\Enums\PromotionOutcome;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\PromotionDecision;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Promotion\PromotionService;
use Illuminate\Validation\ValidationException;

function promotionContext(): array
{
    $fromYear = AcademicYear::factory()->create(['name' => '2026/2027']);
    Semester::factory()->ganjil()->create([
        'academic_year_id' => $fromYear->id,
        'grades_locked_at' => now(),
        'report_published_at' => now(),
    ]);
    Semester::factory()->genap()->create([
        'academic_year_id' => $fromYear->id,
        'grades_locked_at' => now(),
        'report_published_at' => now(),
    ]);
    $toYear = AcademicYear::factory()->create(['name' => '2027/2028']);

    return [$fromYear, $toYear];
}

function promotionStudent(AcademicYear $year, Classroom $classroom): Student
{
    $student = Student::factory()->create();
    ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    return $student;
}

function promotionFailures(Student $student, Semester $semester, int $failed, int $passed = 2): void
{
    $grades = [];
    for ($i = 1; $i <= $failed; $i++) {
        $grades[] = ['subject' => "Nilai rendah {$i}", 'passed' => false];
    }
    for ($i = 1; $i <= $passed; $i++) {
        $grades[] = ['subject' => "Nilai aman {$i}", 'passed' => true];
    }

    ReportCard::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'snapshot' => ['grades' => $grades],
        'generated_at' => now(),
        'published_at' => now(),
    ]);
}

it('promotes at exactly N failing subjects and retains above N', function (): void {
    [$fromYear, $toYear] = promotionContext();
    $ganjil = $fromYear->semesters()->where('name', 'ganjil')->firstOrFail();

    $source = Classroom::factory()->create(['academic_year_id' => $fromYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);
    Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 11, 'major' => ClassMajor::Ipa, 'name' => 'XI IPA-1']);
    Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);

    $atLimit = promotionStudent($fromYear, $source);
    promotionFailures($atLimit, $ganjil, 3);

    $overLimit = promotionStudent($fromYear, $source);
    promotionFailures($overLimit, $ganjil, 4);

    $result = app(PromotionService::class)->propose($fromYear, $toYear);

    $atLimitDecision = PromotionDecision::query()->where('student_id', $atLimit->id)->firstOrFail();
    $overLimitDecision = PromotionDecision::query()->where('student_id', $overLimit->id)->firstOrFail();

    expect($atLimitDecision->outcome)->toBe(PromotionOutcome::Promote)
        ->and($atLimitDecision->toClassroom?->name)->toBe('XI IPA-1')
        ->and($overLimitDecision->outcome)->toBe(PromotionOutcome::Retain)
        ->and($overLimitDecision->toClassroom?->name)->toBe('X-1')
        ->and($result['batch']->status)->toBe(PromotionBatchStatus::Draft)
        ->and($result['promoted'])->toBe(1)
        ->and($result['retained'])->toBe(1);
});

it('graduates grade 12 students and marks them graduated when the batch is applied', function (): void {
    [$fromYear, $toYear] = promotionContext();
    $ganjil = $fromYear->semesters()->where('name', 'ganjil')->firstOrFail();

    $source = Classroom::factory()->create(['academic_year_id' => $fromYear->id, 'grade_level' => 12, 'major' => ClassMajor::Ipa, 'name' => 'XII IPA-1']);

    $student = promotionStudent($fromYear, $source);
    promotionFailures($student, $ganjil, 5);

    $service = app(PromotionService::class);
    $result = $service->propose($fromYear, $toYear);

    $decision = PromotionDecision::query()->where('student_id', $student->id)->firstOrFail();

    expect($decision->outcome)->toBe(PromotionOutcome::Graduate)
        ->and($decision->to_classroom_id)->toBeNull()
        ->and($result['graduated'])->toBe(1);

    $service->apply($result['batch']);

    expect($student->fresh()->status)->toBe(StudentStatus::Graduated)
        ->and(ClassEnrollment::query()->where('academic_year_id', $toYear->id)->count())->toBe(0);
});

it('blocks apply while a semester of the year is unlocked', function (): void {
    [$fromYear, $toYear] = promotionContext();

    $source = Classroom::factory()->create(['academic_year_id' => $fromYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);
    Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 11, 'major' => ClassMajor::Ipa, 'name' => 'XI IPA-1']);

    promotionStudent($fromYear, $source);

    $service = app(PromotionService::class);
    $result = $service->propose($fromYear, $toYear);

    $fromYear->semesters()->where('name', 'genap')->update(['grades_locked_at' => null]);

    expect(fn () => $service->apply($result['batch']))
        ->toThrow(ValidationException::class, 'setelah semua semester terkunci');

    expect($result['batch']->fresh()->status)->toBe(PromotionBatchStatus::Draft)
        ->and(ClassEnrollment::query()->where('academic_year_id', $toYear->id)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'promotion.apply')->count())->toBe(0);
});

it('lists students without a target class and blocks apply until assigned', function (): void {
    [$fromYear, $toYear] = promotionContext();

    $source = Classroom::factory()->create(['academic_year_id' => $fromYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);
    $student = promotionStudent($fromYear, $source);

    $service = app(PromotionService::class);
    $result = $service->propose($fromYear, $toYear);

    expect($result['unassigned'])->toBe([$student->id]);

    expect(fn () => $service->apply($result['batch']))
        ->toThrow(ValidationException::class, 'Penerapan dibatalkan');

    expect($result['batch']->fresh()->status)->toBe(PromotionBatchStatus::Draft)
        ->and(ClassEnrollment::query()->where('academic_year_id', $toYear->id)->count())->toBe(0);
});

it('applies the batch once and treats a second apply as a no-op', function (): void {
    [$fromYear, $toYear] = promotionContext();
    $ganjil = $fromYear->semesters()->where('name', 'ganjil')->firstOrFail();

    $source = Classroom::factory()->create(['academic_year_id' => $fromYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);
    Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 11, 'major' => ClassMajor::Ipa, 'name' => 'XI IPA-1']);
    Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);

    $promoted = promotionStudent($fromYear, $source);
    $retained = promotionStudent($fromYear, $source);
    promotionFailures($retained, $ganjil, 4);

    $service = app(PromotionService::class);
    $result = $service->propose($fromYear, $toYear);

    $service->apply($result['batch']);

    $promotedEnrollment = ClassEnrollment::query()
        ->where('student_id', $promoted->id)
        ->where('academic_year_id', $toYear->id)
        ->firstOrFail();
    $retainedEnrollment = ClassEnrollment::query()
        ->where('student_id', $retained->id)
        ->where('academic_year_id', $toYear->id)
        ->firstOrFail();

    expect($promotedEnrollment->classroom->name)->toBe('XI IPA-1')
        ->and($retainedEnrollment->classroom->name)->toBe('X-1')
        ->and($result['batch']->fresh()->status)->toBe(PromotionBatchStatus::Applied)
        ->and($result['batch']->fresh()->applied_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'promotion.apply')->count())->toBe(1);

    $service->apply($result['batch']->fresh());

    expect(AuditLog::query()->where('action', 'promotion.apply')->count())->toBe(1)
        ->and(ClassEnrollment::query()->where('academic_year_id', $toYear->id)->count())->toBe(2);
});

it('blocks a new proposal after the batch has been applied', function (): void {
    [$fromYear, $toYear] = promotionContext();

    $source = Classroom::factory()->create(['academic_year_id' => $fromYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);
    Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 11, 'major' => ClassMajor::Ipa, 'name' => 'XI IPA-1']);

    promotionStudent($fromYear, $source);

    $service = app(PromotionService::class);
    $result = $service->propose($fromYear, $toYear);
    $service->apply($result['batch']);

    expect(fn () => $service->propose($fromYear, $toYear))
        ->toThrow(ValidationException::class, 'sudah pernah diterapkan');
});

it('overrides a proposed outcome with a required reason', function (): void {
    [$fromYear, $toYear] = promotionContext();

    $source = Classroom::factory()->create(['academic_year_id' => $fromYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);
    Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 11, 'major' => ClassMajor::Ipa, 'name' => 'XI IPA-1']);
    Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);

    $student = promotionStudent($fromYear, $source);

    $service = app(PromotionService::class);
    $result = $service->propose($fromYear, $toYear);
    $decision = PromotionDecision::query()->where('student_id', $student->id)->firstOrFail();

    expect(fn () => $service->override($decision, PromotionOutcome::Retain, '  '))
        ->toThrow(ValidationException::class, 'Alasan perubahan keputusan wajib diisi.');

    expect($decision->fresh()->outcome)->toBe(PromotionOutcome::Promote)
        ->and($decision->fresh()->is_overridden)->toBeFalse();

    $service->override($decision->fresh(), PromotionOutcome::Retain, 'Nilai rapor perlu perbaikan semester depan.');

    $overridden = $decision->fresh();

    expect($overridden->outcome)->toBe(PromotionOutcome::Retain)
        ->and($overridden->is_overridden)->toBeTrue()
        ->and($overridden->override_reason)->toBe('Nilai rapor perlu perbaikan semester depan.')
        ->and($overridden->toClassroom?->name)->toBe('X-1')
        ->and(AuditLog::query()->where('action', 'promotion.override')->count())->toBe(1);
});

it('rejects overrides after the batch has been applied', function (): void {
    [$fromYear, $toYear] = promotionContext();

    $source = Classroom::factory()->create(['academic_year_id' => $fromYear->id, 'grade_level' => 10, 'major' => null, 'name' => 'X-1']);
    Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 11, 'major' => ClassMajor::Ipa, 'name' => 'XI IPA-1']);

    $student = promotionStudent($fromYear, $source);

    $service = app(PromotionService::class);
    $result = $service->propose($fromYear, $toYear);
    $service->apply($result['batch']);

    $decision = PromotionDecision::query()->where('student_id', $student->id)->firstOrFail();

    expect(fn () => $service->override($decision, PromotionOutcome::Retain, 'Alasan uji coba.'))
        ->toThrow(ValidationException::class, 'sudah diterapkan');
});

it('keeps the grade 11 track by default and lets admin pick another per student', function (): void {
    [$fromYear, $toYear] = promotionContext();

    $source = Classroom::factory()->create(['academic_year_id' => $fromYear->id, 'grade_level' => 11, 'major' => ClassMajor::Ipa, 'name' => 'XI IPA-1']);
    $ipaTarget = Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 12, 'major' => ClassMajor::Ipa, 'name' => 'XII IPA-1']);
    $ipsTarget = Classroom::factory()->create(['academic_year_id' => $toYear->id, 'grade_level' => 12, 'major' => ClassMajor::Ips, 'name' => 'XII IPS-1']);

    $student = promotionStudent($fromYear, $source);

    $service = app(PromotionService::class);
    $result = $service->propose($fromYear, $toYear);
    $decision = PromotionDecision::query()->where('student_id', $student->id)->firstOrFail();

    expect($decision->toClassroom?->name)->toBe('XII IPA-1');

    $service->override($decision, PromotionOutcome::Promote, 'Memilih jurusan IPS sesuai minat siswa.', $ipsTarget->id);

    expect($decision->fresh()->to_classroom_id)->toBe($ipsTarget->id)
        ->and($ipaTarget->id)->not->toBe($ipsTarget->id);
});
