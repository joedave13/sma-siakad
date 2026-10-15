<?php

namespace App\Models;

use App\Enums\PromotionBatchStatus;
use Database\Factories\PromotionBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property PromotionBatchStatus $status
 * @property Carbon|null $applied_at
 */
#[Fillable(['from_academic_year_id', 'to_academic_year_id', 'status', 'applied_by', 'applied_at'])]
class PromotionBatch extends Model
{
    /** @use HasFactory<PromotionBatchFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PromotionBatchStatus::class,
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function fromAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'from_academic_year_id');
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function toAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'to_academic_year_id');
    }

    /**
     * @return HasMany<PromotionDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(PromotionDecision::class);
    }
}
