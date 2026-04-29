<?php

use App\Models\SystemLog;

if (! function_exists('system_log')) {
    function system_log(string $action, string $description): SystemLog
    {
        $user = auth()->user();

        return SystemLog::create([
            'user_id' => $user?->id,
            'user_role' => $user?->role ?? 'system',
            'action' => $action,
            'description' => $description,
        ]);
    }
}
