<?php

namespace App\Http\Controllers\Admin\Accounts;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounts\TeacherAccountRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\GeneratedPassword;
use App\Services\MasterDataDeleteGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeacherAccountController extends Controller
{
    public function __construct(private readonly MasterDataDeleteGuard $guard) {}

    public function index(): Response
    {
        $teachers = Teacher::query()
            ->with('user:id,is_active')
            ->orderBy('full_name')
            ->get();

        return Inertia::render('admin/users/Teachers', [
            'teachers' => $teachers
                ->map(fn (Teacher $teacher): array => $this->payload($teacher))
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/TeacherForm', [
            'teacher' => null,
        ]);
    }

    public function store(TeacherAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $password = GeneratedPassword::make();

        DB::transaction(function () use ($validated, $password): void {
            $user = User::query()->create([
                'name' => $validated['name'] ?? '',
                'username' => $validated['nip'] ?? '',
                'role' => Role::Teacher,
                'password' => $password,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $teacher = Teacher::query()->create([
                'user_id' => $user->id,
                'nip' => $validated['nip'] ?? '',
                'full_name' => $user->name,
                'phone' => $validated['phone'] ?? null,
            ]);

            AuditLogger::record('teacher.create', $teacher, [], $teacher->only([
                'nip', 'full_name', 'phone',
            ]));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Akun guru dibuat. Kata sandi awal untuk {$validated['nip']}: {$password} (wajib diganti saat masuk pertama kali).",
        ]);

        return redirect()->route('admin.users.teachers.index');
    }

    public function edit(Teacher $teacher): Response
    {
        return Inertia::render('admin/users/TeacherForm', [
            'teacher' => $this->payload($teacher),
        ]);
    }

    public function update(TeacherAccountRequest $request, Teacher $teacher): RedirectResponse
    {
        $validated = $request->validated();
        $old = $teacher->only(['nip', 'full_name', 'phone']);

        DB::transaction(function () use ($teacher, $validated): void {
            $teacher->user()->firstOrFail()->update([
                'name' => $validated['name'] ?? '',
                'username' => $validated['nip'] ?? '',
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $teacher->update([
                'nip' => $validated['nip'] ?? '',
                'full_name' => $validated['name'] ?? '',
                'phone' => $validated['phone'] ?? null,
            ]);
        });

        AuditLogger::record('teacher.update', $teacher, $old, $teacher->only([
            'nip', 'full_name', 'phone',
        ]));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Akun guru berhasil diperbarui.',
        ]);

        return redirect()->route('admin.users.teachers.index');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $this->guard->blockIfInUse($teacher);

        $old = $teacher->only(['nip', 'full_name', 'phone']);

        DB::transaction(function () use ($teacher): void {
            $teacher->delete();
            $teacher->user()->firstOrFail()->update(['is_active' => false]);
        });

        AuditLogger::record('teacher.delete', $teacher, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Akun guru {$old['nip']} berhasil dihapus.",
        ]);

        return redirect()->route('admin.users.teachers.index');
    }

    /**
     * @return array{id: int, user_id: int, nip: string, full_name: string, phone: string|null, is_active: bool}
     */
    private function payload(Teacher $teacher): array
    {
        return [
            'id' => $teacher->id,
            'user_id' => $teacher->user_id,
            'nip' => $teacher->nip ?? '',
            'full_name' => $teacher->full_name,
            'phone' => $teacher->phone,
            'is_active' => $teacher->user->is_active,
        ];
    }
}
