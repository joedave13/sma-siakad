<?php

namespace App\Http\Controllers\Admin\Accounts;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounts\StudentAccountRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\GeneratedPassword;
use App\Services\MasterDataDeleteGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentAccountController extends Controller
{
    public function __construct(private readonly MasterDataDeleteGuard $guard) {}

    public function index(): Response
    {
        $students = Student::query()
            ->with('user:id,is_active')
            ->orderBy('full_name')
            ->get();

        return Inertia::render('admin/users/Students', [
            'students' => $students
                ->map(fn (Student $student): array => $this->payload($student))
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/StudentForm', [
            'student' => null,
        ]);
    }

    public function store(StudentAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $password = GeneratedPassword::make();

        DB::transaction(function () use ($validated, $password): void {
            $user = User::query()->create([
                'name' => $validated['name'] ?? '',
                'username' => $validated['nisn'] ?? '',
                'role' => Role::Student,
                'password' => $password,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $student = Student::query()->create([
                'user_id' => $user->id,
                'nisn' => $validated['nisn'] ?? '',
                'nis' => $validated['nis'] ?? null,
                'full_name' => $user->name,
                'gender' => $validated['gender'] ?? '',
                'birth_date' => $validated['birth_date'] ?? null,
                'birth_place' => $validated['birth_place'] ?? null,
                'address' => $validated['address'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            AuditLogger::record('student.create', $student, [], $student->only([
                'nisn', 'full_name', 'gender', 'status',
            ]));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Akun siswa dibuat. Kata sandi awal untuk {$validated['nisn']}: {$password} (wajib diganti saat masuk pertama kali).",
        ]);

        return redirect()->route('admin.users.students.index');
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('admin/users/StudentForm', [
            'student' => $this->payload($student),
        ]);
    }

    public function update(StudentAccountRequest $request, Student $student): RedirectResponse
    {
        $validated = $request->validated();
        $old = $student->only(['nisn', 'full_name', 'status']);

        DB::transaction(function () use ($student, $validated): void {
            $student->user()->firstOrFail()->update([
                'name' => $validated['name'] ?? '',
                'username' => $validated['nisn'] ?? '',
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $student->update([
                'nisn' => $validated['nisn'] ?? '',
                'nis' => $validated['nis'] ?? null,
                'full_name' => $validated['name'] ?? '',
                'gender' => $validated['gender'] ?? '',
                'birth_date' => $validated['birth_date'] ?? null,
                'birth_place' => $validated['birth_place'] ?? null,
                'address' => $validated['address'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);
        });

        AuditLogger::record('student.update', $student, $old, $student->only([
            'nisn', 'full_name', 'status',
        ]));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Akun siswa berhasil diperbarui.',
        ]);

        return redirect()->route('admin.users.students.index');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->guard->blockIfInUse($student);

        $old = $student->only(['nisn', 'full_name', 'status']);

        DB::transaction(function () use ($student): void {
            $student->delete();
            $student->user()->firstOrFail()->update(['is_active' => false]);
        });

        AuditLogger::record('student.delete', $student, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Akun siswa {$old['nisn']} berhasil dihapus.",
        ]);

        return redirect()->route('admin.users.students.index');
    }

    /**
     * @return array{id: int, user_id: int, nisn: string, nis: string|null, full_name: string, gender: string, birth_date: string|null, birth_place: string|null, address: string|null, status: string, is_active: bool}
     */
    private function payload(Student $student): array
    {
        return [
            'id' => $student->id,
            'user_id' => $student->user_id,
            'nisn' => $student->nisn,
            'nis' => $student->nis,
            'full_name' => $student->full_name,
            'gender' => $student->gender->value,
            'birth_date' => $student->birth_date?->format('Y-m-d'),
            'birth_place' => $student->birth_place,
            'address' => $student->address,
            'status' => $student->status->value,
            'is_active' => $student->user->is_active,
        ];
    }
}
