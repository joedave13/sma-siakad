<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['key', 'value'])]
class AcademicSetting extends Model
{
    use SoftDeletes;

    /**
     * The school-wide defaults seeded on install.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'predicate_a_min' => '90',
            'predicate_b_min' => '80',
            'predicate_c_min' => '75',
            'promotion_max_failed_subjects' => '3',
            'default_kkm' => '75',
        ];
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::query()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return $setting->value;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) (static::get($key) ?? $default);
    }

    public static function set(string $key, string $value): static
    {
        return static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
