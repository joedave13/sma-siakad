<?php

namespace App\Models;

use App\Enums\PromotionOutcome;
use Database\Factories\PromotionDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property PromotionOutcome $outcome
 */
#[Fillable([
    'promotion_batch_id',
    'student_id',
    'from_classroom_id',
    'to_classroom_id',
    'outcome',
    'is_overridden',
    'override_reason',
])]
class PromotionDecision extends Model
{
    /** @use HasFactory<PromotionDecisionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => PromotionOutcome::class,
            'is_overridden' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PromotionBatch, $this>
     */
    public function promotionBatch(): BelongsTo
    {
        return $this->belongsTo(PromotionBatch::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function fromClassroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'from_classroom_id');
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function toClassroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'to_classroom_id');
    }
}
