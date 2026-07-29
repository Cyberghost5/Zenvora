<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records what administrators did.
 *
 * Every state change an admin can make -- approving money, editing limits,
 * blocking a user -- writes a row here. The admin name is denormalised so the
 * trail stays readable even if the account is later removed.
 */
class AuditLogger
{
    public function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?User $admin = null,
    ): AdminAuditLog {
        $admin ??= Auth::user();

        return AdminAuditLog::query()->create([
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'before' => $before,
            'after' => $after,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Log a settings change, recording only the keys that actually moved.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function logSettingsChange(array $before, array $after): ?AdminAuditLog
    {
        $changedKeys = array_keys(array_filter(
            $after,
            fn ($value, $key) => ! array_key_exists($key, $before) || $before[$key] != $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changedKeys === []) {
            return null;
        }

        return $this->log(
            action: 'settings.updated',
            description: 'Updated: '.implode(', ', $changedKeys),
            before: array_intersect_key($before, array_flip($changedKeys)),
            after: array_intersect_key($after, array_flip($changedKeys)),
        );
    }
}
