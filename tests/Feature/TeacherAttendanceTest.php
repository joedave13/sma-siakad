<?php

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Jobs\RecomputeDailyAttendance;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Guardian;
use App\Models\User;
use App\Notifications\ChildAbsent;
use App\Services\Attendance\AttendanceSubmitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from the attendance index and sheet', function (): void {
    $context = teacherAttendanceUiContext();

    $this->get(route('teacher.attendance.index'))->assertRedirect(route('login'));
    $this->get(route('teacher.attendance.show', $context['schedule']))->assertRedirect(route('login'));
});

it('forbids a student from the teacher attendance routes', function (): void {
    $context = teacherAttendanceUiContext();
    $studentUser = User::factory()->asRole(Role::Student)->create();

    $this->actingAs($studentUser)->get(route('teacher.attendance.index'))->assertForbidden();
    $this->actingAs($studentUser)->get(route('teacher.attendance.show', $context['schedule']))->assertForbidden();
    $this->actingAs($studentUser)->post(route('teacher.attendance.store', $context['schedule']), [
        'date' => '2026-10-12',
        'entries' => [['student_id' => $context['students'][0]->id, 'status' => 'present']],
    ])->assertForbidden();
});

it('lists today\'s teaching periods with their submission state', function (): void {
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $context = teacherAttendanceUiContext();

    $this->actingAs($context['user'])
        ->get(route('teacher.attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/attendance/Index')
            ->where('date', '2026-10-12')
            ->where('profileMissing', false)
            ->has('slots', 1)
            ->where('slots.0.schedule_id', $context['schedule']->id)
            ->where('slots.0.period_number', 1)
            ->where('slots.0.start_time', '07:00')
            ->where('slots.0.end_time', '07:45')
            ->where('slots.0.subject', $context['assignment']->subject->name)
            ->where('slots.0.classroom', $context['assignment']->classroom->name)
            ->where('slots.0.state', 'none')
            ->where('nav.1.items.0.key', 'attendance')
            ->where('nav.1.items.0.href', route('teacher.attendance.index'))
            ->where('nav.1.items.0.coming_soon', false)
        );

    $this->travelBack();
});

it('shows an empty list on a day without classes', function (): void {
    $this->travelTo(Carbon::parse('2026-10-13 08:00'));
    $context = teacherAttendanceUiContext();

    $this->actingAs($context['user'])
        ->get(route('teacher.attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/attendance/Index')
            ->where('date', '2026-10-13')
            ->where('slots', [])
        );

    $this->travelBack();
});

it('falls back to today when the date parameter is invalid', function (): void {
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $context = teacherAttendanceUiContext();

    $this->actingAs($context['user'])
        ->get(route('teacher.attendance.index', ['date' => 'bukan-tanggal']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-12'));

    $this->actingAs($context['user'])
        ->get(route('teacher.attendance.index', ['date' => '2026-02-30']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-12'));

    $this->travelBack();
});

it('marks a teacher profile as missing when no teacher record exists', function (): void {
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $user = User::factory()->asRole(Role::Teacher)->create();

    $this->actingAs($user)
        ->get(route('teacher.attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileMissing', true)
            ->where('slots', [])
        );

    $this->travelBack();
});

it('defaults every roster student to Hadir and exposes Indonesian status labels', function (): void {
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $context = teacherAttendanceUiContext();

    $this->actingAs($context['user'])
        ->get(route('teacher.attendance.show', ['schedule' => $context['schedule'], 'date' => '2026-10-12']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/attendance/Sheet')
            ->where('date', '2026-10-12')
            ->where('slot.schedule_id', $context['schedule']->id)
            ->has('roster', 2)
            ->where('roster.0.full_name', $context['students'][0]->full_name)
            ->where('session.submitted', false)
            ->where('session.editable', true)
            ->where('session.entries', [])
            ->has('status_options', 5)
            ->where('status_options.0', ['value' => 'present', 'label' => 'Hadir'])
            ->where('status_options.4', ['value' => 'late', 'label' => 'Terlambat'])
        );

    $this->travelBack();
});

it('marks a submitted session editable inside the window and locked after it', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $context = teacherAttendanceUiContext();
    $date = Carbon::parse('2026-10-12');

    app(AttendanceSubmitService::class)->submit($context['schedule'], $date, [
        ['student_id' => $context['students'][0]->id, 'status' => 'present'],
    ], $context['user']);

    $this->travelTo($date->copy()->addHours(4));
    $this->actingAs($context['user'])
        ->get(route('teacher.attendance.show', ['schedule' => $context['schedule'], 'date' => '2026-10-12']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.submitted', true)
            ->where('session.editable', true)
            ->where('session.lock_message', null)
            ->where('session.entries', [(string) $context['students'][0]->id => 'present'])
        );

    $this->travelTo($date->copy()->addDays(2));
    $this->actingAs($context['user'])
        ->get(route('teacher.attendance.show', ['schedule' => $context['schedule'], 'date' => '2026-10-12']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.editable', false)
            ->where('session.lock_message', 'Presensi hanya dapat diubah oleh penyusunnya hingga pukul 23:59 sehari setelah tanggal presensi. Setelah itu hanya admin yang dapat mengubah.')
        );

    $this->actingAs($context['user'])
        ->get(route('teacher.attendance.index', ['date' => '2026-10-12']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('slots.0.state', 'locked'));

    $this->travelBack();
});

it('forbids another teacher from opening or submitting the sheet', function (): void {
    $context = teacherAttendanceUiContext();
    $otherTeacherUser = User::factory()->asRole(Role::Teacher)->create();

    $this->actingAs($otherTeacherUser)
        ->get(route('teacher.attendance.show', ['schedule' => $context['schedule'], 'date' => '2026-10-12']))
        ->assertForbidden();

    $this->actingAs($otherTeacherUser)
        ->post(route('teacher.attendance.store', $context['schedule']), [
            'date' => '2026-10-12',
            'entries' => [['student_id' => $context['students'][0]->id, 'status' => 'present']],
        ])
        ->assertForbidden();
});

it('stores a session with rows, queues recompute, and notifies guardians of absentees', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $context = teacherAttendanceUiContext();
    $guardian = Guardian::factory()->create();
    $context['students'][1]->guardians()->attach($guardian->id, ['relationship' => 'ibu']);

    $this->actingAs($context['user'])
        ->post(route('teacher.attendance.store', $context['schedule']), [
            'date' => '2026-10-12',
            'entries' => [
                ['student_id' => $context['students'][0]->id, 'status' => 'present'],
                ['student_id' => $context['students'][1]->id, 'status' => 'absent'],
            ],
        ])
        ->assertRedirect(route('teacher.attendance.show', [
            'schedule' => $context['schedule'],
            'date' => '2026-10-12',
        ]));

    $session = AttendanceSession::query()
        ->where('schedule_id', $context['schedule']->id)
        ->where('date', '2026-10-12')
        ->first();

    expect($session)->not->toBeNull()
        ->and($session->attendances()->count())->toBe(2)
        ->and($session->submitted_by)->toBe($context['user']->id)
        ->and(session('inertia.flash_data')['toast']['message'])->toBe('Presensi berhasil disimpan.');

    Bus::assertDispatched(RecomputeDailyAttendance::class, 2);
    Notification::assertSentTo($guardian, ChildAbsent::class);

    $this->travelBack();
});

it('blocks the teacher after the edit window with an Indonesian message', function (): void {
    Bus::fake([RecomputeDailyAttendance::class]);
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $context = teacherAttendanceUiContext();
    $date = Carbon::parse('2026-10-12');

    app(AttendanceSubmitService::class)->submit($context['schedule'], $date, [
        ['student_id' => $context['students'][0]->id, 'status' => 'present'],
    ], $context['user']);

    $this->travelTo($date->copy()->addDays(2));
    $this->actingAs($context['user'])
        ->post(route('teacher.attendance.store', $context['schedule']), [
            'date' => '2026-10-12',
            'entries' => [
                ['student_id' => $context['students'][0]->id, 'status' => 'absent'],
            ],
        ])
        ->assertSessionHasErrors('attendance');

    expect(session('errors')->first('attendance'))
        ->toBe('Presensi hanya dapat diubah oleh penyusunnya hingga pukul 23:59 sehari setelah tanggal presensi. Setelah itu hanya admin yang dapat mengubah.')
        ->and(Attendance::query()->where('student_id', $context['students'][0]->id)->first()->status)->toBe(AttendanceStatus::Present);

    $this->travelBack();
});

it('checks sheet ownership against the teacher profile, not coincidental ids', function (): void {
    User::factory()->asRole(Role::Teacher)->create();
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $context = teacherAttendanceUiContext();

    expect($context['teacher']->id)->not->toBe($context['user']->id);

    $this->actingAs($context['user'])
        ->get(route('teacher.attendance.show', ['schedule' => $context['schedule'], 'date' => '2026-10-12']))
        ->assertOk();

    $this->actingAs($context['user'])
        ->post(route('teacher.attendance.store', $context['schedule']), [
            'date' => '2026-10-12',
            'entries' => [['student_id' => $context['students'][0]->id, 'status' => 'present']],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teacher.attendance.show', [
            'schedule' => $context['schedule'],
            'date' => '2026-10-12',
        ]));

    expect(AttendanceSession::query()->count())->toBe(1);

    $this->travelBack();
});

it('rejects an unknown attendance status with an Indonesian message', function (): void {
    $context = teacherAttendanceUiContext();

    $this->actingAs($context['user'])
        ->post(route('teacher.attendance.store', $context['schedule']), [
            'date' => '2026-10-12',
            'entries' => [
                ['student_id' => $context['students'][0]->id, 'status' => 'libur'],
            ],
        ])
        ->assertSessionHasErrors('entries.0.status');

    expect(session('errors')->first('entries.0.status'))
        ->toBe('Status kehadiran tidak dikenal.')
        ->and(AttendanceSession::query()->count())->toBe(0);
});
