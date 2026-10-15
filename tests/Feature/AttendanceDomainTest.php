<?php

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Jobs\RecomputeDailyAttendance;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\DailyAttendance;
use App\Models\Guardian;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Notifications\ChildAbsent;
use App\Services\Attendance\AttendanceSubmitService;
use App\Services\Attendance\DailyAttendanceRecomputer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function attendanceContext(): array
{
    $teacher = Teacher::factory()->create();
    $assignment = TeachingAssignment::factory()->create(['teacher_id' => $teacher->id]);
    $period = LessonPeriod::factory()->create(['number' => 1]);
    $schedule = Schedule::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'lesson_period_id' => $period->id,
        'day_of_week' => 'senin',
    ]);
    $students = Student::factory()->count(2)->create();
    foreach ($students as $student) {
        ClassEnrollment::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $assignment->classroom_id,
            'academic_year_id' => $assignment->semester->academic_year_id,
        ]);
    }

    return [$teacher, $schedule, $students];
}

it('submits a session with one bulk upsert and queues recompute', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    [$teacher, $schedule, $students] = attendanceContext();

    $session = app(AttendanceSubmitService::class)->submit(
        $schedule,
        Carbon::parse('2026-10-12'),
        [
            ['student_id' => $students[0]->id, 'status' => 'present'],
            ['student_id' => $students[1]->id, 'status' => AttendanceStatus::Sick],
        ],
        $teacher->user,
    );

    expect(AttendanceSession::query()->count())->toBe(1)
        ->and($session->attendances()->count())->toBe(2)
        ->and($session->submitted_by)->toBe($teacher->user_id)
        ->and($session->attendances()->where('student_id', $students[1]->id)->first()->status)->toBe(AttendanceStatus::Sick);

    Bus::assertDispatched(RecomputeDailyAttendance::class, 2);
});

it('is idempotent on double submit with last write winning', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    [$teacher, $schedule, $students] = attendanceContext();
    $service = app(AttendanceSubmitService::class);
    $date = Carbon::parse('2026-10-12');
    $this->travelTo($date->copy()->addHour());

    $service->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'present'],
    ], $teacher->user);
    $service->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'absent'],
    ], $teacher->user);

    expect(AttendanceSession::query()->count())->toBe(1)
        ->and(Attendance::query()->count())->toBe(1)
        ->and(Attendance::query()->first()->status)->toBe(AttendanceStatus::Absent);

    $this->travelBack();
});

it('lets the submitting teacher edit until 23:59 the next day, then blocks with an Indonesian message', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    [$teacher, $schedule, $students] = attendanceContext();
    $service = app(AttendanceSubmitService::class);
    $date = Carbon::parse('2026-10-12');

    $service->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'present'],
    ], $teacher->user);

    $this->travelTo($date->copy()->addDay()->setTime(23, 59));
    $service->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'sick'],
    ], $teacher->user);

    $this->travelTo($date->copy()->addDays(2));
    expect(fn () => $service->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'absent'],
    ], $teacher->user))->toThrow(ValidationException::class, 'Presensi hanya dapat diubah oleh penyusunnya hingga pukul 23:59 sehari setelah tanggal presensi. Setelah itu hanya admin yang dapat mengubah.');

    $this->travelBack();
});

it('blocks a teacher who is not the submitting teacher after the window and lets admin through with an audit log', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    [$teacher, $schedule, $students] = attendanceContext();
    $service = app(AttendanceSubmitService::class);
    $date = Carbon::parse('2026-10-12');
    $otherTeacherUser = User::factory()->asRole(Role::Teacher)->create();
    $admin = User::factory()->asRole(Role::Admin)->create();

    $service->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'present'],
    ], $teacher->user);

    $this->travelTo($date->copy()->addDays(3));

    expect(fn () => $service->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'absent'],
    ], $otherTeacherUser))->toThrow(ValidationException::class);

    $session = $service->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'absent'],
    ], $admin);

    expect(AuditLog::query()->where('action', 'attendance.override')->count())->toBe(1)
        ->and($session->attendances()->first()->is_overridden)->toBeTrue()
        ->and($session->attendances()->first()->status)->toBe(AttendanceStatus::Absent);

    $this->travelBack();
});

it('rejects an unknown status and ignores non-roster students', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    [$teacher, $schedule, $students] = attendanceContext();
    $outsider = Student::factory()->create();

    expect(fn () => app(AttendanceSubmitService::class)->submit($schedule, Carbon::parse('2026-10-12'), [
        ['student_id' => $students[0]->id, 'status' => 'libur'],
    ], $teacher->user))->toThrow(ValidationException::class);

    $session = app(AttendanceSubmitService::class)->submit($schedule, Carbon::parse('2026-10-12'), [
        ['student_id' => $outsider->id, 'status' => 'present'],
    ], $teacher->user);

    expect($session->attendances()->count())->toBe(0);
});

it('sets the daily status from the first recorded period of the day', function (): void {
    [$teacher, $schedule, $students] = attendanceContext();
    $secondPeriod = LessonPeriod::factory()->create(['number' => 2]);
    $secondSchedule = Schedule::factory()->create([
        'teaching_assignment_id' => $schedule->teaching_assignment_id,
        'lesson_period_id' => $secondPeriod->id,
        'day_of_week' => 'senin',
    ]);
    $service = app(AttendanceSubmitService::class);
    $date = Carbon::parse('2026-10-12');

    $service->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'sick'],
    ], $teacher->user);
    $service->submit($secondSchedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'present'],
    ], $teacher->user);

    $daily = DailyAttendance::query()->where('student_id', $students[0]->id)->first();

    expect($daily->status)->toBe(AttendanceStatus::Sick)
        ->and($daily->is_late)->toBeFalse();
});

it('falls back to the earliest recorded period when the first period is missing and recomputes later', function (): void {
    [$teacher, $schedule, $students] = attendanceContext();
    $secondPeriod = LessonPeriod::factory()->create(['number' => 2]);
    $secondSchedule = Schedule::factory()->create([
        'teaching_assignment_id' => $schedule->teaching_assignment_id,
        'lesson_period_id' => $secondPeriod->id,
        'day_of_week' => 'senin',
    ]);
    $service = app(AttendanceSubmitService::class);
    $date = Carbon::parse('2026-10-12');
    $student = $students[0];

    $service->submit($secondSchedule, $date, [
        ['student_id' => $student->id, 'status' => 'permitted'],
    ], $teacher->user);

    expect(DailyAttendance::query()->where('student_id', $student->id)->first()->status)->toBe(AttendanceStatus::Permitted);

    $service->submit($schedule, $date, [
        ['student_id' => $student->id, 'status' => 'absent'],
    ], $teacher->user);

    expect(DailyAttendance::query()->where('student_id', $student->id)->first()->status)->toBe(AttendanceStatus::Absent);
});

it('turns Terlambat into Hadir with the is_late flag on the daily row', function (): void {
    [$teacher, $schedule, $students] = attendanceContext();
    $date = Carbon::parse('2026-10-12');

    app(AttendanceSubmitService::class)->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'late'],
    ], $teacher->user);

    $daily = DailyAttendance::query()->where('student_id', $students[0]->id)->first();

    expect($daily->status)->toBe(AttendanceStatus::Present)
        ->and($daily->is_late)->toBeTrue();
});

it('queues an in-app absence notification to linked guardians', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    Notification::fake();
    [$teacher, $schedule, $students] = attendanceContext();
    $guardian = Guardian::factory()->create();
    $students[0]->guardians()->attach($guardian->id, ['relationship' => 'ibu']);

    app(AttendanceSubmitService::class)->submit($schedule, Carbon::parse('2026-10-12'), [
        ['student_id' => $students[0]->id, 'status' => 'absent'],
    ], $teacher->user);

    Notification::assertSentTo($guardian, ChildAbsent::class);
});

it('excludes non-active students from the roster', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    [$teacher, $schedule, $students] = attendanceContext();
    $students[0]->update(['status' => StudentStatus::Transferred]);

    $session = app(AttendanceSubmitService::class)->submit($schedule, Carbon::parse('2026-10-12'), [
        ['student_id' => $students[0]->id, 'status' => 'present'],
        ['student_id' => $students[1]->id, 'status' => 'present'],
    ], $teacher->user);

    expect($session->attendances()->count())->toBe(1)
        ->and($session->attendances()->first()->student_id)->toBe($students[1]->id);
});

it('deletes the daily row when no attendance records remain', function (): void {
    [$teacher, $schedule, $students] = attendanceContext();
    $date = Carbon::parse('2026-10-12');

    app(AttendanceSubmitService::class)->submit($schedule, $date, [
        ['student_id' => $students[0]->id, 'status' => 'present'],
    ], $teacher->user);

    expect(DailyAttendance::query()->where('student_id', $students[0]->id)->exists())->toBeTrue();

    Attendance::query()->delete();
    app(DailyAttendanceRecomputer::class)->recompute($students[0]->id, $date);

    expect(DailyAttendance::query()->where('student_id', $students[0]->id)->exists())->toBeFalse();
});
