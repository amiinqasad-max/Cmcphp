<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records admin actions (§33) — login/logout, content create/update/delete/
 * publish, settings changes, user/role changes. Deliberately a thin,
 * explicit service (called from model observers and auth listeners) rather
 * than a magic trait that intercepts every attribute change, so log entries
 * stay meaningful instead of noisy.
 */
class ActivityLogger
{
    public function log(string $action, ?Model $subject = null, array $properties = [], ?User $user = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => ($user ?? Auth::user())?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
