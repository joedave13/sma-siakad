<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record an audit log entry for the authenticated user.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function record(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => auth()->id(),
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'action' => $action,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
        ]);
    }
}
