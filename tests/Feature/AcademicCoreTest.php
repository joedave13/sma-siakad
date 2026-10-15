<?php

use App\Enums\Role;
use App\Models\AcademicSetting;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\LessonPeriod;
use App\Models\Semester;
use App\Models\User;
use App\Services\AuditLogger;

it('seeds the defaults idempotently', function (): void {
    $this->seed();
    $this->seed();

    expect(LessonPeriod::query()->count())->toBe(10)
        ->and(AcademicSetting::query()->count())->toBe(count(AcademicSetting::defaults()))
        ->and(User::query()->where('role', Role::Admin)->count())->toBe(1)
        ->and(AcademicSetting::get('default_kkm'))->toBe('75');
});

it('allows only one active academic year', function (): void {
    $first = AcademicYear::factory()->active()->create();
    $second = AcademicYear::factory()->active()->create();

    expect($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeTrue();
});

it('allows only one active semester', function (): void {
    $first = Semester::factory()->ganjil()->active()->create();
    $second = Semester::factory()->genap()->active()->create();

    expect($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeTrue();
});

it('casts semester name to the SemesterTerm enum', function (): void {
    $semester = Semester::factory()->ganjil()->create();

    expect($semester->name->value)->toBe('ganjil')
        ->and($semester->name->label())->toBe('Ganjil');
});

it('records audit log entries with values and user', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $year = AcademicYear::factory()->create();

    $log = AuditLogger::record(
        action: 'academic_year.update',
        auditable: $year,
        oldValues: ['name' => '2025/2026'],
        newValues: ['name' => '2026/2027'],
    );

    expect(AuditLog::query()->count())->toBe(1)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->auditable_id)->toBe($year->id)
        ->and($log->old_values)->toBe(['name' => '2025/2026'])
        ->and($log->new_values)->toBe(['name' => '2026/2027']);
});

it('keeps lesson periods ordered by number', function (): void {
    LessonPeriod::factory()->create(['number' => 10]);
    LessonPeriod::factory()->create(['number' => 1]);
    LessonPeriod::factory()->create(['number' => 5]);

    $numbers = LessonPeriod::query()->ordered()->pluck('number')->all();

    expect($numbers)->toBe([1, 5, 10]);
});
