<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'npsn',
    'address',
    'city',
    'postal_code',
    'phone',
    'email',
    'headmaster_name',
    'headmaster_nip',
    'logo_path',
    'headmaster_signature_path',
    'updated_by',
])]
class SchoolProfile extends Model
{
    use SoftDeletes;

    /**
     * The single school profile row, if it exists.
     */
    public static function current(): ?static
    {
        return static::query()->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
