<?php

namespace App\Jobs;

use App\Services\Attendance\DailyAttendanceRecomputer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class RecomputeDailyAttendance implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $studentId,
        public string $date,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DailyAttendanceRecomputer $recomputer): void
    {
        $recomputer->recompute($this->studentId, Carbon::parse($this->date));
    }
}
