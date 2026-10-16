<?php

use App\Http\Controllers\Admin\Accounts\AccountImportController;
use App\Http\Controllers\Admin\Accounts\GuardianAccountController;
use App\Http\Controllers\Admin\Accounts\GuardianLinkController;
use App\Http\Controllers\Admin\Accounts\StudentAccountController;
use App\Http\Controllers\Admin\Accounts\TeacherAccountController;
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

    Route::middleware('role:admin')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('import', [AccountImportController::class, 'create'])->name('import.create');
        Route::post('import', [AccountImportController::class, 'store'])->name('import.store');

        Route::resource('students', StudentAccountController::class)
            ->parameters(['students' => 'student'])
            ->except(['show']);

        Route::resource('teachers', TeacherAccountController::class)
            ->parameters(['teachers' => 'teacher'])
            ->except(['show']);

        Route::resource('guardians', GuardianAccountController::class)
            ->parameters(['guardians' => 'guardian'])
            ->except(['show']);

        Route::post('guardians/{guardian}/students', [GuardianLinkController::class, 'store'])
            ->name('guardians.link.store');

        Route::delete('guardians/{guardian}/students/{student}', [GuardianLinkController::class, 'destroy'])
            ->name('guardians.link.destroy');
    });

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
