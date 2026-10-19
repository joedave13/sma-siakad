<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Homeroom\HomeroomNoteRequest;
use App\Models\AcademicSetting;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\ReportCard\ReportCardSnapshotBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HomeroomController extends Controller
{
    public function __construct(private readonly ReportCardSnapshotBuilder $snapshotBuilder) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $teacher = Teacher::query()->where('user_id', $user->getKey())->first();
        $year = AcademicYear::query()->where('is_active', true)->first();
        $semester = Semester::query()->where('is_active', true)->first();

        return Inertia::render('teacher/homeroom/Index', [
            'classrooms' => $this->classroomsPayload($teacher, $year, $semester),
            'profileMissing' => $teacher === null,
            'noActiveYear' => $teacher !== null && $year === null,
            'noActiveSemester' => $teacher !== null && $semester === null,
        ]);
    }

    /**
     * The homeroom classes of one teacher in the active academic year.
     *
     * @return list<array{id: int, name: string, grade_level: int, student_count: int, ready: bool}>
     */
    private function classroomsPayload(?Teacher $teacher, ?AcademicYear $year, ?Semester $semester): array
    {
        if ($teacher === null || $year === null) {
            return [];
        }

        $classrooms = Classroom::query()
            ->where('homeroom_teacher_id', $teacher->getKey())
            ->where('academic_year_id', $year->getKey())
            ->orderBy('name')
            ->get();

        $studentCounts = collect();
        if ($classrooms->isNotEmpty()) {
            $studentCounts = ClassEnrollment::query()
                ->where('academic_year_id', $year->getKey())
                ->whereIn('classroom_id', $classrooms->pluck('id'))
                ->whereHas('student', fn ($query) => $query->where('status', StudentStatus::Active->value))
                ->get(['classroom_id'])
                ->groupBy('classroom_id')
                ->map(fn ($enrollments) => $enrollments->count());
        }

        $payload = [];
        foreach ($classrooms as $classroom) {
            $payload[] = [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'grade_level' => $classroom->grade_level,
                'student_count' => $studentCounts->get($classroom->id) ?? 0,
                'ready' => $semester !== null
                    && AcademicSetting::get($this->readyKey($classroom, $semester)) !== null,
            ];
        }

        return $payload;
    }

    public function show(Request $request, Classroom $classroom): Response
    {
        $user = $request->user();
        abort_unless($classroom->homeroom_teacher_id === $user->teacher?->id, 403);

        $semester = Semester::query()->where('is_active', true)->first();

        if ($semester === null) {
            return Inertia::render('teacher/homeroom/Show', [
                'classroom' => ['id' => $classroom->getKey(), 'name' => $classroom->name, 'grade_level' => $classroom->grade_level],
                'semester' => null,
                'students' => [],
                'ready' => false,
            ]);
        }

        $students = $this->roster($classroom, $semester->academic_year_id);
        $preview = $this->snapshotBuilder->homeroomPreview($classroom, $semester, $students);

        return Inertia::render('teacher/homeroom/Show', [
            'classroom' => ['id' => $classroom->getKey(), 'name' => $classroom->name, 'grade_level' => $classroom->grade_level],
            'semester' => [
                'id' => $semester->getKey(),
                'name' => $semester->name->label(),
                'academic_year' => $semester->academicYear->name,
            ],
            'students' => $students
                ->map(fn (Student $student): array => [
                    'id' => $student->getKey(),
                    'nisn' => $student->nisn,
                    'full_name' => $student->full_name,
                    ...$preview[$student->getKey()],
                ])
                ->values()
                ->all(),
            'ready' => AcademicSetting::get($this->readyKey($classroom, $semester)) !== null,
        ]);
    }

    public function storeNote(HomeroomNoteRequest $request, Classroom $classroom): RedirectResponse
    {
        $user = $request->user();
        abort_unless($classroom->homeroom_teacher_id === $user->teacher?->id, 403);

        $semester = $this->activeSemester('note');
        $validated = $request->validated();

        if (! $this->roster($classroom, $semester->academic_year_id)->contains('id', (int) $validated['student_id'])) {
            throw ValidationException::withMessages([
                'note' => 'Data siswa tidak valid.',
            ]);
        }

        ReportCard::query()->updateOrCreate(
            ['student_id' => (int) $validated['student_id'], 'semester_id' => $semester->getKey()],
            ['homeroom_note' => $validated['note']],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Catatan wali kelas berhasil disimpan.',
        ]);

        return redirect()->route('teacher.homeroom.show', $classroom);
    }

    public function storeReady(Request $request, Classroom $classroom): RedirectResponse
    {
        $user = $request->user();
        abort_unless($classroom->homeroom_teacher_id === $user->teacher?->id, 403);

        $semester = $this->activeSemester('ready');
        $students = $this->roster($classroom, $semester->academic_year_id);

        if ($students->isEmpty()) {
            throw ValidationException::withMessages([
                'ready' => 'Tidak ada siswa aktif di kelas ini untuk ditandai siap.',
            ]);
        }

        $preview = $this->snapshotBuilder->homeroomPreview($classroom, $semester, $students);

        $blockers = [];
        foreach ($students as $student) {
            $missing = $preview[$student->getKey()]['missing'];
            if ($missing !== []) {
                $blockers[] = $student->full_name.' ('.implode(', ', $missing).')';
            }
        }

        if ($blockers !== []) {
            $listing = collect($blockers)
                ->take(5)
                ->implode('; ');
            $suffix = count($blockers) > 5 ? '; dan lainnya.' : '.';

            throw ValidationException::withMessages([
                'ready' => sprintf(
                    'Kelas tidak dapat ditandai siap. Nilai belum lengkap pada %d siswa: %s%s',
                    count($blockers),
                    $listing,
                    $suffix,
                ),
            ]);
        }

        AcademicSetting::set($this->readyKey($classroom, $semester), now()->toIso8601String());
        AuditLogger::record('homeroom.mark_ready', $classroom, [], [
            'semester_id' => $semester->getKey(),
            'students' => $students->count(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Kelas ditandai siap.',
        ]);

        return redirect()->route('teacher.homeroom.show', $classroom);
    }

    /**
     * @throws ValidationException
     */
    private function activeSemester(string $errorKey): Semester
    {
        $semester = Semester::query()->where('is_active', true)->first();

        if ($semester === null) {
            throw ValidationException::withMessages([
                $errorKey => 'Belum ada semester aktif.',
            ]);
        }

        return $semester;
    }

    /**
     * Active students enrolled in the classroom for the academic year.
     *
     * @return Collection<int, Student>
     */
    private function roster(Classroom $classroom, int $academicYearId): Collection
    {
        return Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('classroom_id', $classroom->getKey())
                ->where('academic_year_id', $academicYearId))
            ->orderBy('full_name')
            ->get();
    }

    private function readyKey(Classroom $classroom, Semester $semester): string
    {
        return sprintf('homeroom_ready_%d_%d', $classroom->getKey(), $semester->getKey());
    }
}
