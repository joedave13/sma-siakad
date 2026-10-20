<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentViews\StudentReadPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(private readonly StudentReadPresenter $presenter) {}

    public function schedule(Request $request): Response
    {
        return Inertia::render('student/Schedule', [
            'schedule' => $this->presenter->schedule($this->resolveStudent($request)),
        ]);
    }

    public function attendance(Request $request): Response
    {
        return Inertia::render('student/Attendance', [
            'attendance' => $this->presenter->attendance($this->resolveStudent($request)),
        ]);
    }

    public function grades(Request $request): Response
    {
        return Inertia::render('student/Grades', [
            'grades' => $this->presenter->grades($this->resolveStudent($request)),
        ]);
    }

    public function reportCards(Request $request): Response
    {
        return Inertia::render('student/ReportCards', [
            'reportCards' => $this->presenter->reportCards($this->resolveStudent($request), false),
        ]);
    }

    /**
     * The signed-in student's own record; an account without a student profile 404s.
     */
    private function resolveStudent(Request $request): Student
    {
        return Student::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
