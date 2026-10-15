<?php

use App\Http\Controllers\Admin\ResetUserPasswordController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('force-password-change', [ForcePasswordChangeController::class, 'create'])
        ->name('force-password.change');

    Route::put('force-password-change', [ForcePasswordChangeController::class, 'store'])
        ->name('force-password.update');

    Route::post('admin/users/{user}/reset-password', [ResetUserPasswordController::class, 'store'])
        ->middleware('role:admin')
        ->name('admin.users.reset-password');
});

require __DIR__.'/settings.php';
