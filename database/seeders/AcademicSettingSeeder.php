<?php

namespace Database\Seeders;

use App\Models\AcademicSetting;
use Illuminate\Database\Seeder;

class AcademicSettingSeeder extends Seeder
{
    /**
     * Seed the school-wide academic defaults (idempotent).
     */
    public function run(): void
    {
        foreach (AcademicSetting::defaults() as $key => $value) {
            AcademicSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
