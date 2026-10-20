<?php

use App\Http\Controllers\Admin\Accounts\AccountImportController;
use App\Http\Controllers\Admin\Accounts\GuardianAccountController;
use App\Http\Controllers\Admin\Accounts\GuardianLinkController;
use App\Http\Controllers\Admin\Accounts\StudentAccountController;
use App\Http\Controllers\Admin\Accounts\TeacherAccountController;
use App\Http\Controllers\Admin\Attendance\AdminAttendanceController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Master\AcademicYearController;
use App\Http\Controllers\Admin\Master\ClassroomController;
use App\Http\Controllers\Admin\Master\LessonPeriodController;
use App\Http\Controllers\Admin\Master\SemesterController;
use App\Http\Controllers\Admin\Master\SubjectController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResetUserPasswordController;
use App\Http\Controllers\Admin\Schedule\EnrollmentController;
use App\Http\Controllers\Admin\Schedule\ScheduleController;
use App\Http\Controllers\Admin\Schedule\TeachingAssignmentController;
use App\Http\Controllers\Admin\SchoolProfileController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportCardDownloadController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\GradeController;
use App\Http\Controllers\Teacher\HomeroomController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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

    Route::middleware('role:admin')->prefix('admin/schedule')->name('admin.schedule.')->group(function () {
        Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::delete('enrollments/{classEnrollment}', [EnrollmentController::class, 'destroy'])
            ->name('enrollments.destroy');

        Route::get('assignments', [TeachingAssignmentController::class, 'index'])->name('assignments.index');
        Route::post('assignments', [TeachingAssignmentController::class, 'store'])->name('assignments.store');
        Route::delete('assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'destroy'])
            ->name('assignments.destroy');

        Route::get('weekly', [ScheduleController::class, 'index'])->name('weekly.index');
        Route::post('weekly', [ScheduleController::class, 'store'])->name('weekly.store');
        Route::delete('weekly/{schedule}', [ScheduleController::class, 'destroy'])->name('weekly.destroy');
    });

    Route::middleware('role:teacher')->prefix('teacher')->name('teacher.')->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/{schedule}', [AttendanceController::class, 'show'])->name('attendance.show');
        Route::post('attendance/{schedule}', [AttendanceController::class, 'store'])->name('attendance.store');

        Route::get('grades', [GradeController::class, 'index'])->name('grades.index');
        Route::get('grades/{teachingAssignment}', [GradeController::class, 'show'])->name('grades.show');
        Route::post('grades/{teachingAssignment}', [GradeController::class, 'store'])->name('grades.store');

        Route::get('homeroom', [HomeroomController::class, 'index'])->name('homeroom.index');
        Route::get('homeroom/{classroom}', [HomeroomController::class, 'show'])->name('homeroom.show');
        Route::post('homeroom/{classroom}/notes', [HomeroomController::class, 'storeNote'])->name('homeroom.notes.store');
        Route::post('homeroom/{classroom}/ready', [HomeroomController::class, 'storeReady'])->name('homeroom.ready');
    });

    Route::middleware('role:student')->prefix('rapor')->name('student.report-cards.')->group(function () {
        Route::get('{semester}/unduh', [ReportCardDownloadController::class, 'student'])->name('download');
    });

    Route::middleware('role:parent')->prefix('anak/{student}/rapor')->name('guardian.report-cards.')->group(function () {
        Route::get('{semester}/unduh', [ReportCardDownloadController::class, 'guardian'])->name('download');
    });

    Route::middleware('role:admin')->prefix('admin/attendance')->name('admin.attendance.')->group(function () {
        Route::get('{schedule}', [AdminAttendanceController::class, 'show'])->name('show');
        Route::post('{schedule}', [AdminAttendanceController::class, 'store'])->name('store');
    });

    Route::middleware('role:admin')->prefix('admin/reports')->name('admin.reports.')->group(function () {
        Route::get('', [ReportController::class, 'index'])->name('index');
        Route::get('{semester}', [ReportController::class, 'show'])->name('show');
        Route::post('{semester}/lock', [ReportController::class, 'lock'])->name('lock');
        Route::post('{semester}/unlock', [ReportController::class, 'unlock'])->name('unlock');
        Route::post('{semester}/generate', [ReportController::class, 'generate'])->name('generate');
        Route::post('{semester}/retry', [ReportController::class, 'retry'])->name('retry');
        Route::post('{semester}/publish', [ReportController::class, 'publish'])->name('publish');
    });

    Route::middleware('role:admin')->prefix('admin/promotion')->name('admin.promotion.')->group(function () {
        Route::get('', [PromotionController::class, 'index'])->name('index');
        Route::post('propose', [PromotionController::class, 'propose'])->name('propose');
        Route::post('decisions/{promotionDecision}/override', [PromotionController::class, 'override'])->name('override');
        Route::post('apply', [PromotionController::class, 'apply'])->name('apply');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('school-settings', [SchoolProfileController::class, 'edit'])->name('school-settings.edit');
        Route::put('school-settings', [SchoolProfileController::class, 'update'])->name('school-settings.update');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

require __DIR__.'/settings.php';
