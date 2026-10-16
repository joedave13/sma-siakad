<?php

namespace App\Http\Controllers\Admin\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Schedule\TeachingAssignmentStoreRequest;
use App\Models\Classroom;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Services\AuditLogger;
use App\Services\MasterDataDeleteGuard;
use App\Services\Scheduling\ScheduleCollisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeachingAssignmentController extends Controller
{
    public function __construct(
        private readonly ScheduleCollisionService $collisions,
        private readonly MasterDataDeleteGuard $guard,
    ) {}

    public function index(Request $request): Response
    {
        $semester = Semester::query()
            ->where('is_active', true)
            ->with('academicYear:id,name')
            ->first();

        if ($semester === null) {
            return Inertia::render('admin/schedule/Assignments', [
                'semester' => null,
                'classrooms' => [],
                'subjects' => [],
                'teachers' => [],
                'assignments' => [],
                'selectedClassroom' => null,
            ]);
        }

        $classrooms = array_values(Classroom::query()
            ->where('academic_year_id', $semester->academic_year_id)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Classroom $classroom): array => [
                'id' => $classroom->id,
                'name' => $classroom->name,
            ])
            ->all());

        return Inertia::render('admin/schedule/Assignments', [
            'semester' => [
                'id' => $semester->getKey(),
                'name' => $semester->name->label(),
                'academic_year' => $semester->academicYear->name,
            ],
            'classrooms' => $classrooms,
            'subjects' => Subject::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Subject $subject): array => [
                    'id' => $subject->getKey(),
                    'code' => $subject->code,
                    'name' => $subject->name,
                ])
                ->values()
                ->all(),
            'teachers' => Teacher::query()
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
                ->map(fn (Teacher $teacher): array => [
                    'id' => $teacher->getKey(),
                    'full_name' => $teacher->full_name,
                ])
                ->values()
                ->all(),
            'assignments' => $this->assignmentPayload($semester),
            'selectedClassroom' => $this->selectedClassroom($request, $classrooms),
        ]);
    }

    public function store(TeachingAssignmentStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $semester = Semester::query()->findOrFail((int) $validated['semester_id']);
        $classroom = Classroom::query()->findOrFail((int) $validated['classroom_id']);
        $subject = Subject::query()->findOrFail((int) $validated['subject_id']);
        $teacher = Teacher::query()->findOrFail((int) $validated['teacher_id']);

        $conflict = $this->collisions->assignmentConflictMessage($semester, $classroom, $subject);
        if ($conflict !== null) {
            return back()->withErrors(['subject_id' => $conflict]);
        }

        $assignment = DB::transaction(function () use ($semester, $classroom, $subject, $teacher): TeachingAssignment {
            $existing = TeachingAssignment::withTrashed()
                ->where('semester_id', $semester->getKey())
                ->where('classroom_id', $classroom->getKey())
                ->where('subject_id', $subject->getKey())
                ->first();

            if ($existing !== null) {
                $existing->teacher_id = $teacher->getKey();
                $existing->restore();

                return $existing;
            }

            return TeachingAssignment::query()->create([
                'semester_id' => $semester->getKey(),
                'classroom_id' => $classroom->getKey(),
                'subject_id' => $subject->getKey(),
                'teacher_id' => $teacher->getKey(),
            ]);
        });

        AuditLogger::record('assignment.create', $assignment, [], $assignment->only([
            'semester_id', 'classroom_id', 'subject_id', 'teacher_id',
        ]));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Penugasan {$subject->name} untuk kelas {$classroom->name} ditambahkan.",
        ]);

        return redirect()->route('admin.schedule.assignments.index', [
            'classroom' => $classroom->getKey(),
        ]);
    }

    public function destroy(TeachingAssignment $teachingAssignment): RedirectResponse
    {
        $this->guard->blockIfInUse($teachingAssignment);

        $teachingAssignment->load(['classroom:id,name', 'subject:id,name']);
        $old = $teachingAssignment->only([
            'semester_id', 'classroom_id', 'subject_id', 'teacher_id',
        ]);

        $teachingAssignment->delete();

        AuditLogger::record('assignment.delete', $teachingAssignment, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Penugasan {$teachingAssignment->subject?->name} untuk kelas {$teachingAssignment->classroom?->name} dihapus.",
        ]);

        return redirect()->route('admin.schedule.assignments.index', [
            'classroom' => $old['classroom_id'],
        ]);
    }

    /**
     * The semester's subject-class assignments with resolved names for the
     * per-classroom lists on the assignments tab.
     *
     * @return list<array{id: int, classroom_id: int, classroom_name: string, subject_id: int, subject_name: string, teacher_id: int, teacher_name: string}>
     */
    private function assignmentPayload(Semester $semester): array
    {
        return array_values(TeachingAssignment::query()
            ->where('semester_id', $semester->getKey())
            ->with(['classroom:id,name', 'subject:id,name', 'teacher:id,full_name'])
            ->orderBy('id')
            ->get()
            ->map(fn (TeachingAssignment $assignment): array => [
                'id' => $assignment->id,
                'classroom_id' => $assignment->classroom_id,
                'classroom_name' => $assignment->classroom->name,
                'subject_id' => $assignment->subject_id,
                'subject_name' => $assignment->subject->name,
                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => $assignment->teacher->full_name,
            ])
            ->all());
    }

    /**
     * @param  list<array{id: int, name: string}>  $classrooms
     */
    private function selectedClassroom(Request $request, array $classrooms): ?int
    {
        $requested = (int) $request->query('classroom', '0');

        foreach ($classrooms as $classroom) {
            if ($classroom['id'] === $requested) {
                return $requested;
            }
        }

        return $classrooms[0]['id'] ?? null;
    }
}
