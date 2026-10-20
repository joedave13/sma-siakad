<?php

use App\Enums\GenerationStatus;
use App\Enums\PromotionBatchStatus;
use App\Enums\PromotionOutcome;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\GradeEntry;
use App\Models\Guardian;
use App\Models\PromotionBatch;
use App\Models\PromotionDecision;
use App\Models\ReportCard;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('seeds a complete demo school idempotently', function (): void {
    $this->seed(DemoDataSeeder::class);

    expect(AcademicYear::query()->where('name', '2026/2027')->where('is_active', true)->exists())->toBeTrue()
        ->and(Semester::query()->where('is_active', true)->count())->toBe(1)
        ->and(Subject::query()->count())->toBe(13)
        ->and(Teacher::query()->count())->toBe(8)
        ->and(Classroom::query()->count())->toBe(12)
        ->and(Student::query()->where('status', StudentStatus::Active->value)->count())->toBe(18)
        ->and(Guardian::query()->count())->toBe(16)
        ->and(User::query()->where('role', Role::Parent->value)->count())->toBe(16)
        ->and(ClassEnrollment::query()->count())->toBe(18)
        ->and(TeachingAssignment::query()->count())->toBe(43)
        ->and(Schedule::query()->count())->toBe(86);

    expect(AttendanceSession::query()->count())->toBeGreaterThan(80)
        ->and(Attendance::query()->count())->toBeGreaterThan(200)
        ->and(DailyAttendance::query()->count())->toBeGreaterThan(100)
        ->and(DB::table('notifications')->count())->toBeGreaterThan(0);

    expect(GradeEntry::query()->count())->toBe(772)
        ->and(ReportCard::query()->count())->toBe(18)
        ->and(ReportCard::query()->where('generation_status', GenerationStatus::Done->value)->count())->toBe(16)
        ->and(ReportCard::query()->where('generation_status', GenerationStatus::Failed->value)->count())->toBe(2)
        ->and(ReportCard::query()->whereNotNull('published_at')->count())->toBe(16)
        ->and(Storage::disk('public')->exists('school/logo.png'))->toBeTrue()
        ->and(Semester::query()->where('is_active', true)->value('report_published_at'))->not->toBeNull();

    $batch = PromotionBatch::query()->firstOrFail();

    expect($batch->status)->toBe(PromotionBatchStatus::Draft)
        ->and(PromotionDecision::query()->where('promotion_batch_id', $batch->getKey())->count())->toBe(18)
        ->and(PromotionDecision::query()->where('outcome', PromotionOutcome::Promote->value)->count())->toBe(11)
        ->and(PromotionDecision::query()->where('outcome', PromotionOutcome::Retain->value)->count())->toBe(2)
        ->and(PromotionDecision::query()->where('outcome', PromotionOutcome::Graduate->value)->count())->toBe(5)
        ->and(PromotionDecision::query()->where('is_overridden', true)->count())->toBe(1);

    expect(AuditLog::query()->count())->toBeGreaterThanOrEqual(4);

    $this->seed(DemoDataSeeder::class);

    expect(Student::query()->count())->toBe(18)
        ->and(TeachingAssignment::query()->count())->toBe(43)
        ->and(Schedule::query()->count())->toBe(86)
        ->and(ReportCard::query()->count())->toBe(18)
        ->and(GradeEntry::query()->count())->toBe(772);
});
