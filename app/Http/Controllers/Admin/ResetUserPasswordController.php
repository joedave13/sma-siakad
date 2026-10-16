<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\GeneratedPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResetUserPasswordController extends Controller
{
    /**
     * Reset a user's password and force a change on next login.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $password = GeneratedPassword::make();

        $user->update([
            'password' => $password,
            'must_change_password' => true,
        ]);

        AuditLogger::record('account.password-reset', $user, [
            'must_change_password' => false,
        ], [
            'must_change_password' => true,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kata sandi baru untuk {$user->username}: {$password}",
        ]);

        return back();
    }
}
