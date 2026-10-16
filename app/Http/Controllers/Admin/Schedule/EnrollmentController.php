<?php

namespace App\Http\Controllers\Admin\Schedule;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Schedule\EnrollmentStoreRequest;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\MasterDataDeleteGuard;
use App\Services\Scheduling\ScheduleCollisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly ScheduleCollisionService $collisions,
        private readonly MasterDataDeleteGuard $guard,
    ) {}

    public function index(Request $request): Response
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        if ($academicYear === null) {
            return Inertia::render('admin/schedule/Enrollments', [
                'academicYear' => null,
                'classrooms' => [],
                'students' => [],
                'selectedClassroom' => null,
            ]);
        }

        $classrooms = $this->classroomPayload($academicYear);
        $students = Student::query()
            ->where('status', StudentStatus::Active)
            ->whereDoesntHave('enrollments', fn ($query) => $query
                ->where('academic_year_id', $academicYear->getKey()))
            ->orderBy('full_name')
            ->get(['id', 'nisn', 'full_name'])
            ->map(fn (Student $student): array => [
                'id' => $student->getKey(),
                'nisn' => $student->nisn,
                'full_name' => $student->full_name,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/schedule/Enrollments', [
            'academicYear' => [
                'id' => $academicYear->getKey(),
                'name' => $academicYear->name,
            ],
            'classrooms' => $classrooms,
            'students' => $students,
            'selectedClassroom' => $this->selectedClassroom($request, $classrooms),
        ]);
    }

    public function store(EnrollmentStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $classroom = Classroom::query()->findOrFail((int) $validated['classroom_id']);
        $student = Student::query()->findOrFail((int) $validated['student_id']);

        $conflict = $this->collisions->enrollmentConflictMessage($student, $classroom->academicYear);
        if ($conflict !== null) {
            return back()->withErrors(['student_id' => $conflict]);
        }

        $enrollment = DB::transaction(function () use ($classroom, $student): ClassEnrollment {
            $existing = ClassEnrollment::withTrashed()
                ->where('student_id', $student->getKey())
                ->where('academic_year_id', $classroom->academic_year_id)
                ->first();

            if ($existing !== null) {
                $existing->classroom_id = $classroom->getKey();
                $existing->restore();

                return $existing;
            }

            return ClassEnrollment::query()->create([
                'classroom_id' => $classroom->getKey(),
                'student_id' => $student->getKey(),
                'academic_year_id' => $classroom->academic_year_id,
            ]);
        });

        AuditLogger::record('enrollment.create', $enrollment, [], $enrollment->only([
            'classroom_id', 'student_id', 'academic_year_id',
        ]));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$student->full_name} ditempatkan di kelas {$classroom->name}.",
        ]);

        return redirect()->route('admin.schedule.enrollments.index', [
            'classroom' => $classroom->getKey(),
        ]);
    }

    public function destroy(ClassEnrollment $classEnrollment): RedirectResponse
    {
        $this->guard->blockIfInUse($classEnrollment);

        $classEnrollment->load(['student:id,full_name', 'classroom:id,name']);
        $old = $classEnrollment->only(['classroom_id', 'student_id', 'academic_year_id']);

        $classEnrollment->delete();

        AuditLogger::record('enrollment.delete', $classEnrollment, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$classEnrollment->student?->full_name} dikeluarkan dari kelas {$classEnrollment->classroom?->name}.",
        ]);

        return redirect()->route('admin.schedule.enrollments.index', [
            'classroom' => $old['classroom_id'],
        ]);
    }

    /**
     * Classrooms of the active year with their enrolled students, ordered by
     * grade level, each student row carrying the enrollment id used to unassign.
     *
     * @return list<array{id: int, name: string, students: list<array{enrollment_id: int, id: int, nisn: string, full_name: string}>}>
     */
    private function classroomPayload(AcademicYear $academicYear): array
    {
        return array_values(Classroom::query()
            ->where('academic_year_id', $academicYear->getKey())
            ->with('enrollments.student:id,full_name,nisn')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get()
            ->map(function (Classroom $classroom): array {
                $students = [];

                foreach ($classroom->enrollments as $enrollment) {
                    $student = $enrollment->student;

                    if ($student === null) {
                        continue;
                    }

                    $students[] = [
                        'enrollment_id' => $enrollment->id,
                        'id' => $student->id,
                        'nisn' => $student->nisn,
                        'full_name' => $student->full_name,
                    ];
                }

                usort($students, fn (array $a, array $b): int => $a['full_name'] <=> $b['full_name']);

                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'students' => $students,
                ];
            })
            ->all());
    }

    /**
     * @param  list<array{id: int, name: string, students: list<array{enrollment_id: int, id: int, nisn: string, full_name: string}>}>  $classrooms
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
