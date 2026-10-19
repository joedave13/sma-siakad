<?php

use App\Enums\GradeComponent;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\ClassEnrollment;
use App\Models\GradeEntry;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from the homeroom routes', function (): void {
    $context = teacherHomeroomUiContext();

    $this->get(route('teacher.homeroom.index'))->assertRedirect(route('login'));
    $this->get(route('teacher.homeroom.show', $context['classroom']))->assertRedirect(route('login'));
    $this->post(route('teacher.homeroom.notes.store', $context['classroom']), [
        'student_id' => $context['students'][0]->id,
        'note' => 'Catatan.',
    ])->assertRedirect(route('login'));
    $this->post(route('teacher.homeroom.ready', $context['classroom']))->assertRedirect(route('login'));
});

it('forbids a student from the homeroom routes', function (): void {
    $context = teacherHomeroomUiContext();
    $studentUser = User::factory()->asRole(Role::Student)->create();

    $this->actingAs($studentUser)->get(route('teacher.homeroom.index'))->assertForbidden();
    $this->actingAs($studentUser)->get(route('teacher.homeroom.show', $context['classroom']))->assertForbidden();
    $this->actingAs($studentUser)->post(route('teacher.homeroom.notes.store', $context['classroom']), [
        'student_id' => $context['students'][0]->id,
        'note' => 'Catatan.',
    ])->assertForbidden();
    $this->actingAs($studentUser)->post(route('teacher.homeroom.ready', $context['classroom']))->assertForbidden();
});

it('lists the homeroom classroom with its student count and ready state', function (): void {
    $context = teacherHomeroomUiContext();

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/homeroom/Index')
            ->where('profileMissing', false)
            ->where('noActiveYear', false)
            ->where('noActiveSemester', false)
            ->has('classrooms', 1)
            ->where('classrooms.0.id', $context['classroom']->id)
            ->where('classrooms.0.name', $context['classroom']->name)
            ->where('classrooms.0.grade_level', $context['classroom']->grade_level)
            ->where('classrooms.0.student_count', 2)
            ->where('classrooms.0.ready', false)
        );
});

it('marks the homeroom profile as missing when no teacher record exists', function (): void {
    $user = User::factory()->asRole(Role::Teacher)->create();

    $this->actingAs($user)
        ->get(route('teacher.homeroom.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileMissing', true)
            ->where('classrooms', [])
        );
});

it('falls back to empty homeroom states when no year or semester is active', function (): void {
    $context = teacherHomeroomUiContext();

    AcademicYear::query()->update(['is_active' => false]);

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('noActiveYear', true)
            ->where('classrooms', [])
        );

    AcademicYear::query()->whereKey($context['semester']->academic_year_id)->update(['is_active' => true]);
    Semester::query()->update(['is_active' => false]);

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('noActiveYear', false)
            ->where('noActiveSemester', true)
        );
});

it('renders an empty homeroom detail without an active semester', function (): void {
    $context = teacherHomeroomUiContext();
    Semester::query()->update(['is_active' => false]);

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.show', $context['classroom']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/homeroom/Show')
            ->where('semester', null)
            ->where('students', [])
            ->where('ready', false)
        );
});

it('renders every student with subject grades, predicates and the saved note', function (): void {
    $context = teacherHomeroomUiContext();

    $components = [
        [GradeComponent::Assignment, 'Tugas 1', 80],
        [GradeComponent::Quiz, 'Kuis 1', 90],
        [GradeComponent::Midterm, null, 75],
        [GradeComponent::Final, null, 85],
    ];

    foreach ($context['students'] as $student) {
        foreach ($components as [$component, $title, $score]) {
            GradeEntry::factory()->create([
                'teaching_assignment_id' => $context['assignment']->id,
                'student_id' => $student->id,
                'component' => $component,
                'title' => $title,
                'score' => $score,
            ]);
        }

        ReportCard::query()->create([
            'student_id' => $student->id,
            'semester_id' => $context['semester']->id,
            'homeroom_note' => 'Pantau perkembangan akademiknya.',
        ]);
    }

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.show', $context['classroom']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/homeroom/Show')
            ->where('classroom.name', $context['classroom']->name)
            ->where('semester.name', 'Ganjil')
            ->has('students', 2)
            ->where('students.0.missing', [])
            ->has('students.0.grades', 1)
            ->where('students.0.grades.0.subject', $context['assignment']->subject->name)
            ->where('students.0.grades.0.score', 83)
            ->where('students.0.grades.0.predicate', 'B')
            ->where('students.0.homeroom_note', 'Pantau perkembangan akademiknya.')
            ->where('ready', false)
        );
});

it('saves a homeroom note and shows it on the next load', function (): void {
    $context = teacherHomeroomUiContext();

    foreach ($context['students'] as $student) {
        $this->actingAs($context['user'])
            ->post(route('teacher.homeroom.notes.store', $context['classroom']), [
                'student_id' => $student->id,
                'note' => 'Pantau perkembangan akademiknya.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teacher.homeroom.show', $context['classroom']));
    }

    expect(session('inertia.flash_data')['toast']['message'])->toBe('Catatan wali kelas berhasil disimpan.')
        ->and(ReportCard::query()->where('student_id', $context['students'][0]->id)->where('semester_id', $context['semester']->id)->value('homeroom_note'))
        ->toBe('Pantau perkembangan akademiknya.');

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.show', $context['classroom']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.0.homeroom_note', 'Pantau perkembangan akademiknya.')
            ->where('students.1.homeroom_note', 'Pantau perkembangan akademiknya.')
        );
});

it('saves a homeroom note even when the semester is locked', function (): void {
    $context = teacherHomeroomUiContext();
    $context['semester']->update(['grades_locked_at' => now()]);

    $this->actingAs($context['user'])
        ->post(route('teacher.homeroom.notes.store', $context['classroom']), [
            'student_id' => $context['students'][0]->id,
            'note' => 'Catatan kenaikan kelas.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teacher.homeroom.show', $context['classroom']));

    expect(ReportCard::query()->where('student_id', $context['students'][0]->id)->value('homeroom_note'))
        ->toBe('Catatan kenaikan kelas.');
});

it('rejects a note longer than 300 characters with the Indonesian message', function (): void {
    $context = teacherHomeroomUiContext();

    $this->actingAs($context['user'])
        ->post(route('teacher.homeroom.notes.store', $context['classroom']), [
            'student_id' => $context['students'][0]->id,
            'note' => str_repeat('a', 301),
        ])
        ->assertSessionHasErrors('note');

    expect(session('errors')->first('note'))
        ->toBe('Catatan wali kelas maksimal 300 karakter.')
        ->and(ReportCard::query()->count())->toBe(0);
});

it('rejects a note for a student outside the class roster', function (): void {
    $context = teacherHomeroomUiContext();
    $foreignStudent = Student::factory()->create();

    $this->actingAs($context['user'])
        ->post(route('teacher.homeroom.notes.store', $context['classroom']), [
            'student_id' => $foreignStudent->id,
            'note' => 'Catatan.',
        ])
        ->assertSessionHasErrors('note');

    expect(session('errors')->first('note'))
        ->toBe('Data siswa tidak valid.')
        ->and(ReportCard::query()->count())->toBe(0);
});

it('marks the class ready once every student has complete grades', function (): void {
    $context = teacherHomeroomUiContext();

    $components = [
        [GradeComponent::Assignment, 'Tugas 1', 80],
        [GradeComponent::Quiz, 'Kuis 1', 90],
        [GradeComponent::Midterm, null, 75],
        [GradeComponent::Final, null, 85],
    ];

    foreach ($context['students'] as $student) {
        foreach ($components as [$component, $title, $score]) {
            GradeEntry::factory()->create([
                'teaching_assignment_id' => $context['assignment']->id,
                'student_id' => $student->id,
                'component' => $component,
                'title' => $title,
                'score' => $score,
            ]);
        }
    }

    $this->actingAs($context['user'])
        ->post(route('teacher.homeroom.ready', $context['classroom']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teacher.homeroom.show', $context['classroom']));

    expect(session('inertia.flash_data')['toast']['message'])->toBe('Kelas ditandai siap.')
        ->and(AuditLog::query()->where('action', 'homeroom.mark_ready')->where('auditable_id', $context['classroom']->id)->count())->toBe(1);

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('classrooms.0.ready', true));

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.show', $context['classroom']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('ready', true));
});

it('blocks marking ready while any student has missing grades', function (): void {
    $context = teacherHomeroomUiContext();
    $incompleteStudent = $context['students'][1];

    GradeEntry::factory()->create([
        'teaching_assignment_id' => $context['assignment']->id,
        'student_id' => $context['students'][0]->id,
        'component' => GradeComponent::Assignment,
        'title' => 'Tugas 1',
        'score' => 80,
    ]);
    GradeEntry::factory()->create([
        'teaching_assignment_id' => $context['assignment']->id,
        'student_id' => $context['students'][0]->id,
        'component' => GradeComponent::Quiz,
        'title' => 'Kuis 1',
        'score' => 90,
    ]);
    GradeEntry::factory()->create([
        'teaching_assignment_id' => $context['assignment']->id,
        'student_id' => $context['students'][0]->id,
        'component' => GradeComponent::Midterm,
        'title' => null,
        'score' => 75,
    ]);
    GradeEntry::factory()->create([
        'teaching_assignment_id' => $context['assignment']->id,
        'student_id' => $context['students'][0]->id,
        'component' => GradeComponent::Final,
        'title' => null,
        'score' => 85,
    ]);

    $this->actingAs($context['user'])
        ->post(route('teacher.homeroom.ready', $context['classroom']))
        ->assertSessionHasErrors('ready');

    expect(session('errors')->first('ready'))
        ->toStartWith('Kelas tidak dapat ditandai siap. Nilai belum lengkap pada 1 siswa: '.$incompleteStudent->full_name.' (')
        ->toContain($context['assignment']->subject->name)
        ->toEndWith('.');

    expect(AuditLog::query()->where('action', 'homeroom.mark_ready')->exists())->toBeFalse();

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.show', $context['classroom']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('ready', false));
});

it('blocks marking ready when the class has no active students', function (): void {
    $context = teacherHomeroomUiContext();
    ClassEnrollment::query()->where('classroom_id', $context['classroom']->id)->delete();

    $this->actingAs($context['user'])
        ->post(route('teacher.homeroom.ready', $context['classroom']))
        ->assertSessionHasErrors('ready');

    expect(session('errors')->first('ready'))
        ->toBe('Tidak ada siswa aktif di kelas ini untuk ditandai siap.');

    $this->actingAs($context['user'])
        ->get(route('teacher.homeroom.show', $context['classroom']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('students', [])
            ->where('ready', false)
        );
});

it('forbids another teacher from the homeroom detail, note and ready routes', function (): void {
    User::factory()->asRole(Role::Teacher)->create();
    $context = teacherHomeroomUiContext();
    $otherTeacherUser = User::factory()->asRole(Role::Teacher)->create();

    expect($context['teacher']->id)->not->toBe($context['user']->id);

    $this->actingAs($otherTeacherUser)
        ->get(route('teacher.homeroom.show', $context['classroom']))
        ->assertForbidden();

    $this->actingAs($otherTeacherUser)
        ->post(route('teacher.homeroom.notes.store', $context['classroom']), [
            'student_id' => $context['students'][0]->id,
            'note' => 'Catatan.',
        ])
        ->assertForbidden();

    $this->actingAs($otherTeacherUser)
        ->post(route('teacher.homeroom.ready', $context['classroom']))
        ->assertForbidden();

    expect(ReportCard::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'homeroom.mark_ready')->exists())->toBeFalse();
});
