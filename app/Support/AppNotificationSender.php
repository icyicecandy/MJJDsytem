<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class AppNotificationSender
{
    public static function send(User $user, string $title, ?string $message = null, ?string $url = null, string $type = 'general', array $data = [], ?int $createdBy = null): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user->id,
            'created_by' => $createdBy,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'data' => $data,
        ]);
    }

    public static function sendToUsers(iterable $users, string $title, ?string $message = null, ?string $url = null, string $type = 'general', array $data = [], ?int $createdBy = null): Collection
    {
        return collect($users)
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->map(fn (User $user) => self::send($user, $title, $message, $url, $type, $data, $createdBy))
            ->values();
    }

    public static function sendToRole(string $role, string $title, ?string $message = null, ?string $url = null, string $type = 'general', array $data = [], ?int $createdBy = null): Collection
    {
        return self::sendToUsers(
            User::query()->where('role', $role)->get(),
            $title,
            $message,
            $url,
            $type,
            $data,
            $createdBy
        );
    }

    public static function sendOrRefreshUnread(User $user, string $title, ?string $message, ?string $url, string $type, array $data = [], ?int $createdBy = null): AppNotification
    {
        $query = $user->appNotifications()
            ->unread()
            ->where('type', $type);

        if (isset($data['customer_id'])) {
            $query->where('data->customer_id', $data['customer_id']);
        }

        $notification = $query->first();

        if (! $notification) {
            return self::send($user, $title, $message, $url, $type, $data, $createdBy);
        }

        $notification->forceFill([
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'data' => $data,
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        return $notification;
    }
}
