<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackStaffPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->isTechnician())) {
            $updates = [];
            $now = now();

            if (! $user->is_online) {
                $updates['is_online'] = true;
            }

            if ($user->chat_available === null) {
                $updates['chat_available'] = true;
            }

            if (! $user->last_seen || $user->last_seen->diffInSeconds($now) >= 60) {
                $updates['last_seen'] = $now;
            }

            if ($updates !== []) {
                $user->forceFill($updates)->saveQuietly();
            }
        }

        return $next($request);
    }
}
