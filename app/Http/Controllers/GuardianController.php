<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\StudentViews\StudentReadPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    public function __construct(private readonly StudentReadPresenter $presenter) {}

    public function schedule(Request $request): Response
    {
        $guardian = $this->resolveGuardian($request);
        $child = $this->resolveChild($request, $guardian);

        return Inertia::render('parent/Schedule', [
            'schedule' => $child !== null ? $this->presenter->schedule($child) : null,
            ...$this->childProps($guardian, $child),
        ]);
    }

    public function attendance(Request $request): Response
    {
        $guardian = $this->resolveGuardian($request);
        $child = $this->resolveChild($request, $guardian);

        return Inertia::render('parent/Attendance', [
            'attendance' => $child !== null ? $this->presenter->attendance($child) : null,
            ...$this->childProps($guardian, $child),
        ]);
    }

    public function grades(Request $request): Response
    {
        $guardian = $this->resolveGuardian($request);
        $child = $this->resolveChild($request, $guardian);

        return Inertia::render('parent/Grades', [
            'grades' => $child !== null ? $this->presenter->grades($child) : null,
            ...$this->childProps($guardian, $child),
        ]);
    }

    public function reportCards(Request $request): Response
    {
        $guardian = $this->resolveGuardian($request);
        $child = $this->resolveChild($request, $guardian);

        return Inertia::render('parent/ReportCards', [
            'reportCards' => $child !== null
                ? $this->presenter->reportCards($child, true)
                : null,
            ...$this->childProps($guardian, $child),
        ]);
    }

    private function resolveGuardian(Request $request): Guardian
    {
        return Guardian::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    /**
     * The child selected by ?anak=, defaulting to the first linked child.
     * A requested id outside the guardian's links is a 403 (P1 AC), not a 404:
     * the parent already knows the student exists.
     */
    private function resolveChild(Request $request, Guardian $guardian): ?Student
    {
        $students = $guardian->students;
        $requested = $request->query('anak');

        if (blank($requested)) {
            return $students->first();
        }

        abort_unless(
            is_string($requested) || is_int($requested),
            403,
            'Anda tidak memiliki akses ke data siswa tersebut.',
        );

        abort_unless(
            $students->contains(fn (Student $student): bool => (string) $student->getKey() === (string) $requested),
            403,
            'Anda tidak memiliki akses ke data siswa tersebut.',
        );

        return $students->firstWhere('id', $requested);
    }

    /**
     * @return array{children: list<array{id: int, name: string, classroom: string|null}>, currentChildId: int|null}
     */
    private function childProps(Guardian $guardian, ?Student $child): array
    {
        $year = AcademicYear::query()->where('is_active', true)->first();

        return [
            'children' => $guardian->students
                ->map(fn (Student $student): array => [
                    'id' => $student->getKey(),
                    'name' => $student->full_name,
                    'classroom' => $year !== null ? $student->classroomIn($year->id)?->name : null,
                ])
                ->values()
                ->all(),
            'currentChildId' => $child?->getKey(),
        ];
    }
}
