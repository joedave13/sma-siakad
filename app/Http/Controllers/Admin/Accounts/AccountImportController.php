<?php

namespace App\Http\Controllers\Admin\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounts\AccountImportRequest;
use App\Services\AuditLogger;
use App\Services\Import\UserImporter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('admin/users/Import', []);
    }

    public function store(AccountImportRequest $request, UserImporter $importer): RedirectResponse
    {
        $result = $importer->import(
            (string) $request->validated('type'),
            $request->file('file'),
        );

        if ($result['errors'] !== []) {
            Inertia::flash('importErrors', $result['errors']);

            return redirect()->route('admin.users.import.create');
        }

        $typeLabel = match ((string) $request->validated('type')) {
            'student' => 'siswa',
            'teacher' => 'guru',
            'parent' => 'orang tua',
            default => 'akun',
        };

        AuditLogger::record('account.import', null, [], [
            'type' => (string) $request->validated('type'),
            'count' => $result['created'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Berhasil mengimpor {$result['created']} akun {$typeLabel}. Kata sandi awal bersama: {$result['password']} (wajib diganti saat masuk pertama kali).",
        ]);

        return redirect()->route('admin.users.import.create');
    }
}
