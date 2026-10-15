<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ResetUserPasswordController extends Controller
{
    /**
     * Reset a user's password and force a change on next login.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $password = Str::lower(Str::random(8)).random_int(1000, 9999);

        $user->update([
            'password' => $password,
            'must_change_password' => true,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kata sandi baru untuk {$user->username}: {$password}",
        ]);

        return back();
    }
}
