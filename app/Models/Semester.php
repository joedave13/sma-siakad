<?php

namespace App\Models;

use App\Enums\SemesterTerm;
use Database\Factories\SemesterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['academic_year_id', 'name', 'is_active', 'grades_locked_at', 'report_published_at'])]
class Semester extends Model
{
    /** @use HasFactory<SemesterFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => SemesterTerm::class,
            'is_active' => 'boolean',
            'grades_locked_at' => 'datetime',
            'report_published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isLocked(): bool
    {
        return $this->grades_locked_at !== null;
    }

    public function isPublished(): bool
    {
        return $this->report_published_at !== null;
    }

    /**
     * Keep at most one semester active at a time.
     */
    protected static function booted(): void
    {
        static::saving(function (Semester $semester): void {
            if ($semester->is_active && $semester->isDirty('is_active')) {
                static::query()
                    ->whereKeyNot($semester->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }
}
