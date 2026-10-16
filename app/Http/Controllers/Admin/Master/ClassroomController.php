<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Master\ClassroomRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\MasterDataDeleteGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    /**
     * @var list<string>
     */
    private const AUDIT_FIELDS = ['academic_year_id', 'name', 'grade_level', 'major', 'homeroom_teacher_id'];

    public function __construct(private readonly MasterDataDeleteGuard $guard) {}

    public function index(): Response
    {
        $classrooms = Classroom::query()
            ->with(['academicYear:id,name', 'homeroomTeacher:id,full_name'])
            ->orderByDesc('academic_year_id')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/master/Classrooms', [
            'classrooms' => $classrooms,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/master/ClassroomForm', [
            'academicYears' => $this->academicYears(),
            'teachers' => $this->teachers(),
            'classroom' => null,
        ]);
    }

    public function store(ClassroomRequest $request): RedirectResponse
    {
        $classroom = Classroom::query()->create($request->validated());

        AuditLogger::record('classroom.create', $classroom, [], $classroom->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Ruang kelas berhasil ditambahkan.',
        ]);

        return redirect()->route('admin.master.classrooms.index');
    }

    public function edit(Classroom $classroom): Response
    {
        return Inertia::render('admin/master/ClassroomForm', [
            'academicYears' => $this->academicYears(),
            'teachers' => $this->teachers(),
            'classroom' => $classroom,
        ]);
    }

    public function update(ClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $old = $classroom->only(self::AUDIT_FIELDS);

        $classroom->update($request->validated());

        AuditLogger::record('classroom.update', $classroom, $old, $classroom->only(self::AUDIT_FIELDS));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Ruang kelas berhasil diperbarui.',
        ]);

        return redirect()->route('admin.master.classrooms.index');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $this->guard->blockIfInUse($classroom);

        $old = $classroom->only(self::AUDIT_FIELDS);
        $classroom->delete();

        AuditLogger::record('classroom.delete', $classroom, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Ruang kelas berhasil dihapus.',
        ]);

        return redirect()->route('admin.master.classrooms.index');
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    private function academicYears(): Collection
    {
        return AcademicYear::query()
            ->orderByDesc('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Teacher>
     */
    private function teachers(): Collection
    {
        return Teacher::query()
            ->orderBy('full_name')
            ->get(['id', 'full_name']);
    }
}
