<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<string, mixed>|null $snapshot
 * @property GenerationStatus|null $generation_status
 * @property string|null $generation_error
 * @property string|null $pdf_path
 * @property CarbonImmutable|null $generated_at
 * @property CarbonImmutable|null $published_at
 */
#[Fillable([
    'student_id',
    'semester_id',
    'homeroom_note',
    'promotion_outcome',
    'pdf_path',
    'generation_status',
    'generation_error',
    'snapshot',
    'generated_at',
    'published_at',
])]
class ReportCard extends Model
{
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'generation_status' => GenerationStatus::class,
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
