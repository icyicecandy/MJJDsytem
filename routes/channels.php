<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{userId}', function ($user, $userId) {
    // only allow the authenticated user to listen to their own channel
    return (int) $user->id === (int) $userId;
});
