<?php

use App\Enums\GradeComponent;
use App\Enums\Role;
use App\Models\GradeEntry;
use App\Models\GradeRemark;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from the grade index and grid', function (): void {
    $context = teacherAttendanceUiContext();

    $this->get(route('teacher.grades.index'))->assertRedirect(route('login'));
    $this->get(route('teacher.grades.show', $context['assignment']))->assertRedirect(route('login'));
    $this->post(route('teacher.grades.store', $context['assignment']), [
        'entries' => [['student_id' => $context['students'][0]->id, 'component' => 'midterm', 'score' => '80']],
    ])->assertRedirect(route('login'));
});

it('forbids a student from the teacher grade routes', function (): void {
    $context = teacherAttendanceUiContext();
    $studentUser = User::factory()->asRole(Role::Student)->create();

    $this->actingAs($studentUser)->get(route('teacher.grades.index'))->assertForbidden();
    $this->actingAs($studentUser)->get(route('teacher.grades.show', $context['assignment']))->assertForbidden();
    $this->actingAs($studentUser)->post(route('teacher.grades.store', $context['assignment']), [
        'entries' => [['student_id' => $context['students'][0]->id, 'component' => 'midterm', 'score' => '80']],
    ])->assertForbidden();
});

it('lists the teacher assignments with counts and enables the grades and homeroom nav items', function (): void {
    $context = teacherAttendanceUiContext();
    $semester = $context['assignment']->semester;

    $this->actingAs($context['user'])
        ->get(route('teacher.grades.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/grades/Index')
            ->where('profileMissing', false)
            ->where('semester.name', 'Ganjil')
            ->where('semester.academic_year', $semester->academicYear->name)
            ->has('assignments', 1)
            ->where('assignments.0.id', $context['assignment']->id)
            ->where('assignments.0.subject', $context['assignment']->subject->name)
            ->where('assignments.0.kkm', $context['assignment']->subject->kkm)
            ->where('assignments.0.classroom', $context['assignment']->classroom->name)
            ->where('assignments.0.student_count', 2)
            ->where('nav.1.items.1.key', 'grades')
            ->where('nav.1.items.1.href', route('teacher.grades.index'))
            ->where('nav.1.items.1.coming_soon', false)
            ->where('nav.1.items.2.key', 'homeroom')
            ->where('nav.1.items.2.coming_soon', false)
        );
});

it('marks the teacher profile as missing when no teacher record exists', function (): void {
    $user = User::factory()->asRole(Role::Teacher)->create();

    $this->actingAs($user)
        ->get(route('teacher.grades.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileMissing', true)
            ->where('assignments', [])
        );
});

it('shares an empty assignment list when the teacher has no assignments', function (): void {
    $teacher = Teacher::factory()->create();

    $this->actingAs($teacher->user)
        ->get(route('teacher.grades.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileMissing', false)
            ->where('assignments', [])
        );
});

it('shares a null semester payload when no semester is active', function (): void {
    $context = teacherAttendanceUiContext();
    Semester::query()->update(['is_active' => false]);

    $this->actingAs($context['user'])
        ->get(route('teacher.grades.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('semester', null)
            ->where('assignments', [])
        );
});

it('renders the grade grid with columns, cells and weighted final scores', function (): void {
    $context = teacherAttendanceUiContext();

    foreach ($context['students'] as $student) {
        GradeEntry::factory()->create([
            'teaching_assignment_id' => $context['assignment']->id,
            'student_id' => $student->id,
            'component' => GradeComponent::Assignment,
            'title' => 'Tugas 1',
            'score' => 80,
        ]);
        GradeEntry::factory()->create([
            'teaching_assignment_id' => $context['assignment']->id,
            'student_id' => $student->id,
            'component' => GradeComponent::Quiz,
            'title' => 'Kuis 1',
            'score' => 90,
        ]);
        GradeEntry::factory()->create([
            'teaching_assignment_id' => $context['assignment']->id,
            'student_id' => $student->id,
            'component' => GradeComponent::Midterm,
            'title' => null,
            'score' => 75,
        ]);
        GradeEntry::factory()->create([
            'teaching_assignment_id' => $context['assignment']->id,
            'student_id' => $student->id,
            'component' => GradeComponent::Final,
            'title' => null,
            'score' => 85,
        ]);
        GradeRemark::factory()->create([
            'teaching_assignment_id' => $context['assignment']->id,
            'student_id' => $student->id,
            'remark' => 'Perlu bimbingan.',
        ]);
    }

    $this->actingAs($context['user'])
        ->get(route('teacher.grades.show', $context['assignment']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/grades/Grid')
            ->where('assignment.subject', $context['assignment']->subject->name)
            ->where('assignment.kkm', $context['assignment']->subject->kkm)
            ->where('assignment.classroom', $context['assignment']->classroom->name)
            ->where('semester.locked', false)
            ->where('columns.assignment', ['Tugas 1'])
            ->where('columns.quiz', ['Kuis 1'])
            ->has('students', 2)
            ->has('students.0.cells', 4)
            ->where('students.0.cells.0.component', 'assignment')
            ->where('students.0.cells.0.title', 'Tugas 1')
            ->where('students.0.cells.0.score', '80.00')
            ->where('students.0.remark', 'Perlu bimbingan.')
            ->where('students.0.final_score', 83)
            ->where('students.1.final_score', 83)
        );
});

it('saves the whole grid with remarks and redirects with a toast', function (): void {
    $context = teacherAttendanceUiContext();
    $entries = [];

    foreach ($context['students'] as $student) {
        $entries[] = ['student_id' => $student->id, 'component' => 'assignment', 'title' => 'Tugas 1', 'score' => '80', 'expected_updated_at' => null];
        $entries[] = ['student_id' => $student->id, 'component' => 'quiz', 'title' => 'Kuis 1', 'score' => '90', 'expected_updated_at' => null];
        $entries[] = ['student_id' => $student->id, 'component' => 'midterm', 'title' => null, 'score' => '75', 'expected_updated_at' => null];
        $entries[] = ['student_id' => $student->id, 'component' => 'final', 'title' => null, 'score' => '85', 'expected_updated_at' => null];
    }

    $this->actingAs($context['user'])
        ->post(route('teacher.grades.store', $context['assignment']), [
            'entries' => $entries,
            'remarks' => [['student_id' => $context['students'][0]->id, 'remark' => 'Bimbingan rutin.']],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teacher.grades.show', $context['assignment']));

    expect(session('inertia.flash_data')['toast']['message'])->toBe('Nilai berhasil disimpan.')
        ->and(GradeEntry::query()->count())->toBe(8)
        ->and(GradeEntry::query()->where('component', 'midterm')->first()->score)->toBe('75.00')
        ->and(GradeRemark::query()->count())->toBe(1)
        ->and(GradeRemark::query()->first()->remark)->toBe('Bimbingan rutin.');

    $this->actingAs($context['user'])
        ->get(route('teacher.grades.show', $context['assignment']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.0.final_score', 83)
            ->where('students.1.final_score', 83)
        );
});

it('rolls back the whole grid and names the student when a cell is stale', function (): void {
    $context = teacherAttendanceUiContext();
    $staleStudent = $context['students'][0];
    $otherStudent = $context['students'][1];

    $entry = GradeEntry::factory()->create([
        'teaching_assignment_id' => $context['assignment']->id,
        'student_id' => $staleStudent->id,
        'component' => GradeComponent::Midterm,
        'title' => null,
        'score' => 90,
    ]);

    $this->actingAs($context['user'])
        ->post(route('teacher.grades.store', $context['assignment']), [
            'entries' => [
                ['student_id' => $staleStudent->id, 'component' => 'midterm', 'title' => null, 'score' => '85', 'expected_updated_at' => '2000-01-01T00:00:00.000000Z'],
                ['student_id' => $otherStudent->id, 'component' => 'quiz', 'title' => 'Kuis 1', 'score' => '88', 'expected_updated_at' => null],
            ],
        ])
        ->assertSessionHasErrors('grades');

    expect(session('errors')->first('grades'))
        ->toBe(sprintf(
            'Nilai %s (UTS) telah diubah oleh pengguna lain. Nilai saat ini: 90.00. Muat ulang halaman untuk melihat data terbaru.',
            $staleStudent->full_name,
        ));

    expect(GradeEntry::query()->count())->toBe(1)
        ->and($entry->fresh()->score)->toBe('90.00');
});

it('persists a new column only when it carries at least one score', function (): void {
    $context = teacherAttendanceUiContext();
    $blankCells = [
        ['student_id' => $context['students'][0]->id, 'component' => 'assignment', 'title' => 'Tugas Baru', 'score' => null, 'expected_updated_at' => null],
        ['student_id' => $context['students'][1]->id, 'component' => 'assignment', 'title' => 'Tugas Baru', 'score' => null, 'expected_updated_at' => null],
    ];

    $this->actingAs($context['user'])
        ->post(route('teacher.grades.store', $context['assignment']), ['entries' => $blankCells])
        ->assertSessionHasNoErrors();

    expect(GradeEntry::query()->count())->toBe(0);

    $blankCells[0]['score'] = '77';

    $this->actingAs($context['user'])
        ->post(route('teacher.grades.store', $context['assignment']), ['entries' => $blankCells])
        ->assertSessionHasNoErrors();

    expect(GradeEntry::query()->count())->toBe(2)
        ->and(GradeEntry::query()->where('student_id', $context['students'][0]->id)->first()->score)->toBe('77.00')
        ->and(GradeEntry::query()->where('student_id', $context['students'][1]->id)->first()->score)->toBeNull();
});

it('clears an existing score when the cell is emptied', function (): void {
    $context = teacherAttendanceUiContext();
    $entry = GradeEntry::factory()->create([
        'teaching_assignment_id' => $context['assignment']->id,
        'student_id' => $context['students'][0]->id,
        'component' => GradeComponent::Midterm,
        'title' => null,
        'score' => 75,
    ]);

    $this->actingAs($context['user'])
        ->post(route('teacher.grades.store', $context['assignment']), [
            'entries' => [[
                'student_id' => $context['students'][0]->id,
                'component' => 'midterm',
                'title' => null,
                'score' => null,
                'expected_updated_at' => $entry->updated_at->toJSON(),
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect($entry->fresh()->score)->toBeNull()
        ->and(GradeEntry::query()->count())->toBe(1);
});

it('blocks grid saves once the semester is locked', function (): void {
    $context = teacherAttendanceUiContext();
    $context['assignment']->semester->update(['grades_locked_at' => now()]);

    $this->actingAs($context['user'])
        ->post(route('teacher.grades.store', $context['assignment']), [
            'entries' => [[
                'student_id' => $context['students'][0]->id,
                'component' => 'midterm',
                'title' => null,
                'score' => '80',
                'expected_updated_at' => null,
            ]],
        ])
        ->assertSessionHasErrors('grades');

    expect(session('errors')->first('grades'))
        ->toBe('Nilai tidak dapat diubah karena semester telah dikunci.')
        ->and(GradeEntry::query()->count())->toBe(0);
});

it('rejects an out-of-range score with the Indonesian message', function (): void {
    $context = teacherAttendanceUiContext();

    $this->actingAs($context['user'])
        ->post(route('teacher.grades.store', $context['assignment']), [
            'entries' => [[
                'student_id' => $context['students'][0]->id,
                'component' => 'midterm',
                'title' => null,
                'score' => '101',
                'expected_updated_at' => null,
            ]],
        ])
        ->assertSessionHasErrors('grades');

    expect(session('errors')->first('grades'))
        ->toBe('Nilai harus antara 0 dan 100.')
        ->and(GradeEntry::query()->count())->toBe(0);
});

it('rejects a grid containing a student outside the roster', function (): void {
    $context = teacherAttendanceUiContext();
    $foreignStudent = Student::factory()->create();

    $this->actingAs($context['user'])
        ->post(route('teacher.grades.store', $context['assignment']), [
            'entries' => [[
                'student_id' => $foreignStudent->id,
                'component' => 'midterm',
                'title' => null,
                'score' => '80',
                'expected_updated_at' => null,
            ]],
        ])
        ->assertSessionHasErrors('grades');

    expect(session('errors')->first('grades'))
        ->toBe('Data siswa tidak valid.')
        ->and(GradeEntry::query()->count())->toBe(0);
});

it('forbids another teacher from opening or posting the grade grid', function (): void {
    User::factory()->asRole(Role::Teacher)->create();
    $context = teacherAttendanceUiContext();
    $otherTeacherUser = User::factory()->asRole(Role::Teacher)->create();

    expect($context['teacher']->id)->not->toBe($context['user']->id);

    $this->actingAs($otherTeacherUser)
        ->get(route('teacher.grades.show', $context['assignment']))
        ->assertForbidden();

    $this->actingAs($otherTeacherUser)
        ->post(route('teacher.grades.store', $context['assignment']), [
            'entries' => [[
                'student_id' => $context['students'][0]->id,
                'component' => 'midterm',
                'title' => null,
                'score' => '80',
                'expected_updated_at' => null,
            ]],
        ])
        ->assertForbidden();

    expect(GradeEntry::query()->count())->toBe(0);
});
