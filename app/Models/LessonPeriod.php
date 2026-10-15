<?php

namespace App\Models;

use Database\Factories\LessonPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['number', 'start_time', 'end_time'])]
class LessonPeriod extends Model
{
    /** @use HasFactory<LessonPeriodFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
        ];
    }

    /**
     * Order periods by their number.
     *
     * @param  Builder<LessonPeriod>  $query
     * @return Builder<LessonPeriod>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('number');
    }
}
