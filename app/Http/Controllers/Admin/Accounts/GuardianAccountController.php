<?php

namespace App\Http\Controllers\Admin\Accounts;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounts\GuardianAccountRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\GeneratedPassword;
use App\Services\MasterDataDeleteGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GuardianAccountController extends Controller
{
    public function __construct(private readonly MasterDataDeleteGuard $guard) {}

    public function index(): Response
    {
        $links = $this->linksFor();

        $guardians = Guardian::query()
            ->with(['user:id,is_active', 'students:id,full_name,nisn'])
            ->orderBy('full_name')
            ->get();

        return Inertia::render('admin/users/Guardians', [
            'guardians' => $guardians
                ->map(fn (Guardian $guardian): array => $this->payload($guardian, $links))
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/GuardianForm', [
            'guardian' => null,
            'availableStudents' => [],
            'totalStudents' => 0,
        ]);
    }

    public function store(GuardianAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $password = GeneratedPassword::make();

        DB::transaction(function () use ($validated, $password): void {
            $user = User::query()->create([
                'name' => $validated['name'] ?? '',
                'username' => $validated['telepon'] ?? '',
                'role' => Role::Parent,
                'password' => $password,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $guardian = Guardian::query()->create([
                'user_id' => $user->id,
                'full_name' => $user->name,
                'phone' => $validated['telepon'] ?? '',
            ]);

            AuditLogger::record('guardian.create', $guardian, [], $guardian->only([
                'phone', 'full_name',
            ]));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Akun orang tua dibuat. Kata sandi awal untuk {$validated['telepon']}: {$password} (wajib diganti saat masuk pertama kali). Buka Ubah untuk menautkan anak.",
        ]);

        return redirect()->route('admin.users.guardians.index');
    }

    public function edit(Guardian $guardian): Response
    {
        $linkedIds = $guardian->students()->pluck('students.id');

        $availableStudents = Student::query()
            ->whereNotIn('id', $linkedIds)
            ->orderBy('full_name')
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'nisn' => $student->nisn,
                'full_name' => $student->full_name,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/users/GuardianForm', [
            'guardian' => $this->payload($guardian, $this->linksFor([$guardian->getKey()])),
            'availableStudents' => $availableStudents,
            'totalStudents' => Student::query()->count(),
        ]);
    }

    public function update(GuardianAccountRequest $request, Guardian $guardian): RedirectResponse
    {
        $validated = $request->validated();
        $old = $guardian->only(['phone', 'full_name']);

        DB::transaction(function () use ($guardian, $validated): void {
            $guardian->user()->firstOrFail()->update([
                'name' => $validated['name'] ?? '',
                'username' => $validated['telepon'] ?? '',
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $guardian->update([
                'full_name' => $validated['name'] ?? '',
                'phone' => $validated['telepon'] ?? '',
            ]);
        });

        AuditLogger::record('guardian.update', $guardian, $old, $guardian->only([
            'phone', 'full_name',
        ]));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Akun orang tua berhasil diperbarui.',
        ]);

        return redirect()->route('admin.users.guardians.index');
    }

    public function destroy(Guardian $guardian): RedirectResponse
    {
        $this->guard->blockIfInUse($guardian);

        $old = $guardian->only(['phone', 'full_name']);

        DB::transaction(function () use ($guardian): void {
            $guardian->delete();
            $guardian->user()->firstOrFail()->update(['is_active' => false]);
        });

        AuditLogger::record('guardian.delete', $guardian, $old, []);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Akun orang tua {$old['phone']} berhasil dihapus.",
        ]);

        return redirect()->route('admin.users.guardians.index');
    }

    /**
     * @param  array<int, array<int, string>>  $links  guardian id => student id => relationship
     * @return array{id: int, user_id: int, telepon: string, full_name: string, is_active: bool, children: array<int, array{id: int, nisn: string, full_name: string, relationship: string}>}
     */
    private function payload(Guardian $guardian, array $links): array
    {
        $relationships = $links[$guardian->getKey()] ?? [];

        return [
            'id' => $guardian->id,
            'user_id' => $guardian->user_id,
            'telepon' => $guardian->phone ?? '',
            'full_name' => $guardian->full_name,
            'is_active' => $guardian->user->is_active,
            'children' => $guardian->students
                ->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'nisn' => $student->nisn,
                    'full_name' => $student->full_name,
                    'relationship' => $relationships[$student->getKey()] ?? '',
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int>|null  $guardianIds
     * @return array<int, array<int, string>>
     */
    private function linksFor(?array $guardianIds = null): array
    {
        $query = DB::table('guardian_student')
            ->select(['guardian_id', 'student_id', 'relationship']);

        if ($guardianIds !== null) {
            $query->whereIn('guardian_id', $guardianIds);
        }

        $links = [];

        foreach ($query->get() as $row) {
            $links[(int) $row->guardian_id][(int) $row->student_id] = (string) $row->relationship;
        }

        return $links;
    }
}
