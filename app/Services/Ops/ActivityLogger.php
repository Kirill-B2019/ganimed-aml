<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Ops;

use App\Models\ActivityLog;
use App\Models\Check;
use App\Models\User;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(?User $user, string $action, ?Check $check = null, array $meta = []): void
    {
        ActivityLog::query()->create([
            'user_id' => $user?->id,
            'check_id' => $check?->id,
            'case_id' => $check?->case_id,
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
