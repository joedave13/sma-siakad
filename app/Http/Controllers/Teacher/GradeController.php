<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grades\GradeStoreRequest;
use App\Models\ClassEnrollment;
use App\Models\GradeEntry;
use App\Models\GradeRemark;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Services\Grades\FinalScoreCalculator;
use App\Services\Grades\GradeEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    public function __construct(
        private readonly GradeEntryService $entryService,
        private readonly FinalScoreCalculator $calculator,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $teacher = Teacher::query()->where('user_id', $user->getKey())->first();
        $semester = Semester::query()->where('is_active', true)->first();

        return Inertia::render('teacher/grades/Index', [
            'semester' => $semester === null ? null : [
                'name' => $semester->name->label(),
                'academic_year' => $semester->academicYear->name,
            ],
            'assignments' => $this->assignmentsPayload($teacher, $semester),
            'profileMissing' => $teacher === null,
        ]);
    }

    public function show(Request $request, TeachingAssignment $teachingAssignment): Response
    {
        $user = $request->user();
        abort_unless($teachingAssignment->teacher_id === $user->teacher?->id, 403);

        return Inertia::render('teacher/grades/Grid', $this->gridPayload($teachingAssignment));
    }

    public function store(GradeStoreRequest $request, TeachingAssignment $teachingAssignment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($teachingAssignment->teacher_id === $user->teacher?->id, 403);

        $validated = $request->validated();

        $this->entryService->saveGrid(
            $teachingAssignment,
            $validated['entries'],
            $validated['remarks'] ?? [],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Nilai berhasil disimpan.',
        ]);

        return redirect()->route('teacher.grades.show', $teachingAssignment);
    }

    /**
     * The grade assignments of one teacher in the active semester.
     *
     * @return list<array{id: int, subject: string, kkm: int, classroom: string, student_count: int}>
     */
    private function assignmentsPayload(?Teacher $teacher, ?Semester $semester): array
    {
        if ($teacher === null || $semester === null) {
            return [];
        }

        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $teacher->getKey())
            ->where('semester_id', $semester->getKey())
            ->with(['subject:id,name,kkm', 'classroom:id,name'])
            ->get();

        $studentCounts = collect();
        if ($assignments->isNotEmpty()) {
            $studentCounts = ClassEnrollment::query()
                ->where('academic_year_id', $semester->academic_year_id)
                ->whereIn('classroom_id', $assignments->pluck('classroom_id'))
                ->whereHas('student', fn ($query) => $query->where('status', StudentStatus::Active->value))
                ->get(['classroom_id'])
                ->groupBy('classroom_id')
                ->map(fn ($enrollments) => $enrollments->count());
        }

        $payload = [];
        foreach ($assignments as $assignment) {
            $payload[] = [
                'id' => $assignment->id,
                'subject' => $assignment->subject->name,
                'kkm' => $assignment->subject->kkm,
                'classroom' => $assignment->classroom->name,
                'student_count' => $studentCounts->get($assignment->classroom_id) ?? 0,
            ];
        }

        return $payload;
    }

    /**
     * The full grade-grid payload for one teaching assignment.
     *
     * @return array{assignment: array{id: int, subject: string, kkm: int, classroom: string}, semester: array{name: string, academic_year: string, locked: bool}, columns: array{assignment: list<string>, quiz: list<string>}, students: list<array{id: int, nisn: string, full_name: string, cells: list<array{component: string, title: string|null, score: string|null, updated_at: string}>, remark: string|null, final_score: int|null}>}
     */
    private function gridPayload(TeachingAssignment $assignment): array
    {
        $semester = $assignment->semester;
        $entries = GradeEntry::query()
            ->where('teaching_assignment_id', $assignment->getKey())
            ->orderBy('id')
            ->get();

        $remarks = [];
        foreach (GradeRemark::query()->where('teaching_assignment_id', $assignment->getKey())->get() as $remarkRow) {
            $remarks[$remarkRow->student_id] = $remarkRow->remark;
        }

        $columns = ['assignment' => [], 'quiz' => []];
        foreach ($entries as $entry) {
            $component = $entry->component->value;
            if ($entry->title !== null
                && ($component === 'assignment' || $component === 'quiz')
                && ! in_array($entry->title, $columns[$component], true)) {
                $columns[$component][] = $entry->title;
            }
        }

        $students = [];
        foreach ($this->entryService->roster($assignment) as $student) {
            $cells = [];
            $scoreRows = [];

            foreach ($entries->where('student_id', $student->id) as $entry) {
                $cells[] = [
                    'component' => $entry->component->value,
                    'title' => $entry->title,
                    'score' => $entry->score === null ? null : (string) $entry->score,
                    'updated_at' => $entry->updated_at->toJSON(),
                ];
                $scoreRows[] = ['component' => $entry->component, 'score' => $entry->score];
            }

            $students[] = [
                'id' => $student->id,
                'nisn' => $student->nisn,
                'full_name' => $student->full_name,
                'cells' => $cells,
                'remark' => $remarks[$student->id] ?? null,
                'final_score' => $this->calculator->finalScore($scoreRows),
            ];
        }

        return [
            'assignment' => [
                'id' => $assignment->id,
                'subject' => $assignment->subject->name,
                'kkm' => $assignment->subject->kkm,
                'classroom' => $assignment->classroom->name,
            ],
            'semester' => [
                'name' => $semester->name->label(),
                'academic_year' => $semester->academicYear->name,
                'locked' => $semester->isLocked(),
            ],
            'columns' => $columns,
            'students' => $students,
        ];
    }
}
