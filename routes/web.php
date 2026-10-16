<?php

use App\Http\Controllers\Admin\Master\AcademicYearController;
use App\Http\Controllers\Admin\Master\ClassroomController;
use App\Http\Controllers\Admin\Master\LessonPeriodController;
use App\Http\Controllers\Admin\Master\SemesterController;
use App\Http\Controllers\Admin\Master\SubjectController;
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

    Route::middleware('role:admin')->prefix('admin/master')->name('admin.master.')->group(function () {
        Route::resource('academic-years', AcademicYearController::class)
            ->parameters(['academic-years' => 'academicYear'])
            ->except(['show']);

        Route::resource('semesters', SemesterController::class)
            ->parameters(['semesters' => 'semester'])
            ->except(['show']);

        Route::resource('lesson-periods', LessonPeriodController::class)
            ->parameters(['lesson-periods' => 'lessonPeriod'])
            ->except(['show']);

        Route::resource('subjects', SubjectController::class)
            ->except(['show']);

        Route::resource('classrooms', ClassroomController::class)
            ->except(['show']);
    });
});

require __DIR__.'/settings.php';
