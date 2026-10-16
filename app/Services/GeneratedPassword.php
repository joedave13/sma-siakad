<?php

namespace App\Services;

use Illuminate\Support\Str;

class GeneratedPassword
{
    /**
     * The initial password shown exactly once at creation time; only its
     * hash is stored afterwards. Shared by manual account creation, import
     * batches and admin resets so every reset follows the same format.
     */
    public static function make(): string
    {
        return Str::lower(Str::random(8)).random_int(1000, 9999);
    }
}
