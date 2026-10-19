<?php

use App\Enums\Role;
use App\Jobs\RecomputeDailyAttendance;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Attendance\AttendanceSubmitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

it('forbids a teacher from the admin attendance routes', function (): void {
    $context = teacherAttendanceUiContext();
    $teacherUser = $context['user'];

    $this->actingAs($teacherUser)
        ->get(route('admin.attendance.show', ['schedule' => $context['schedule'], 'date' => '2026-10-12']))
        ->assertForbidden();

    $this->actingAs($teacherUser)
        ->post(route('admin.attendance.store', $context['schedule']), [
            'date' => '2026-10-12',
            'entries' => [['student_id' => $context['students'][0]->id, 'status' => 'present']],
        ])
        ->assertForbidden();
});

it('lets an admin override a locked session with an audit log', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $context = teacherAttendanceUiContext();
    $admin = User::factory()->asRole(Role::Admin)->create();
    $date = Carbon::parse('2026-10-12');

    app(AttendanceSubmitService::class)->submit($context['schedule'], $date, [
        ['student_id' => $context['students'][0]->id, 'status' => 'present'],
    ], $context['user']);

    $this->travelTo($date->copy()->addDays(2));

    $this->actingAs($admin)
        ->get(route('admin.attendance.show', ['schedule' => $context['schedule'], 'date' => '2026-10-12']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/attendance/Sheet')
            ->where('session.editable', true)
        );

    $this->actingAs($admin)
        ->post(route('admin.attendance.store', $context['schedule']), [
            'date' => '2026-10-12',
            'entries' => [
                ['student_id' => $context['students'][0]->id, 'status' => 'absent'],
            ],
        ])
        ->assertRedirect(route('admin.attendance.show', [
            'schedule' => $context['schedule'],
            'date' => '2026-10-12',
        ]));

    $attendance = Attendance::query()->where('student_id', $context['students'][0]->id)->first();

    expect($attendance->status->value)->toBe('absent')
        ->and($attendance->is_overridden)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'attendance.override')->count())->toBe(1)
        ->and(session('inertia.flash_data')['toast']['message'])->toBe('Presensi berhasil disimpan.');

    $this->travelBack();
});

it('lists missing sessions on the admin dashboard until they are submitted', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $context = teacherAttendanceUiContext();
    $admin = User::factory()->asRole(Role::Admin)->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('missingSessions', 1)
            ->where('missingSessions.0.schedule_id', $context['schedule']->id)
            ->where('missingSessions.0.subject', $context['assignment']->subject->name)
            ->where('missingSessions.0.classroom', $context['assignment']->classroom->name)
            ->where('missingSessions.0.teacher', $context['teacher']->full_name)
            ->where('missingSessions.0.date', '2026-10-12')
        );

    $this->actingAs($context['user'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('missingSessions', null));

    app(AttendanceSubmitService::class)->submit($context['schedule'], Carbon::parse('2026-10-12'), [
        ['student_id' => $context['students'][0]->id, 'status' => 'present'],
    ], $context['user']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('missingSessions', []));

    $this->travelBack();
});

it('shares an empty missing-session list when no schedule exists', function (): void {
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $admin = User::factory()->asRole(Role::Admin)->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('missingSessions', []));

    $this->travelBack();
});
