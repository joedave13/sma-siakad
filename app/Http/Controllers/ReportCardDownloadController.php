<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCardDownloadController extends Controller
{
    /**
     * Download the signed-in student's published report card PDF.
     */
    public function student(Request $request, Semester $semester): StreamedResponse
    {
        $student = Student::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return $this->download($student, $semester);
    }

    /**
     * Download a linked child's published report card PDF.
     */
    public function guardian(Request $request, Student $student, Semester $semester): StreamedResponse
    {
        $guardian = Guardian::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($guardian->students()->whereKey($student->getKey())->exists(), 403);

        return $this->download($student, $semester);
    }

    private function download(Student $student, Semester $semester): StreamedResponse
    {
        $reportCard = ReportCard::query()
            ->where('student_id', $student->getKey())
            ->where('semester_id', $semester->getKey())
            ->first();

        abort_if($reportCard === null, 404);
        abort_unless($reportCard->isPublished(), 403, 'Rapor belum diterbitkan.');
        abort_if(blank($reportCard->pdf_path), 404, 'File rapor tidak ditemukan.');

        $disk = Storage::disk('public');
        $path = (string) $reportCard->pdf_path;

        abort_unless($disk->exists($path), 404, 'File rapor tidak ditemukan.');

        return $disk->download(
            $path,
            sprintf('rapor-%s-%s.pdf', $student->nisn, Str::lower($semester->name->label())),
        );
    }
}
