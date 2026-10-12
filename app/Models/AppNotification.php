<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use HasFactory;

    public const SIDEBAR_SECTIONS = [
        'dashboard',
        'applications',
        'installations',
        'customers',
        'technicians',
        'tickets',
        'chat',
    ];

    protected $fillable = [
        'user_id',
        'created_by',
        'type',
        'title',
        'message',
        'url',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function sidebarSectionFor(User $user): ?string
    {
        $type = strtolower((string) $this->type);
        $role = (string) $user->role;

        if (str_starts_with($type, 'chat_')) {
            return 'chat';
        }

        if (str_starts_with($type, 'ticket_')) {
            return 'tickets';
        }

        if (str_starts_with($type, 'technician_unavailability')) {
            return $role === 'admin' ? 'technicians' : 'dashboard';
        }

        if (str_starts_with($type, 'customer_')) {
            return $role === 'admin' ? 'customers' : 'dashboard';
        }

        if ($type === 'task_overdue') {
            return ($this->data['task_type'] ?? null) === 'ticket' ? 'tickets' : 'installations';
        }

        if (str_starts_with($type, 'installation_')) {
            return $role === 'customer' ? 'applications' : 'installations';
        }

        if (str_starts_with($type, 'application_')) {
            return $role === 'admin' ? 'applications' : ($role === 'customer' ? 'applications' : 'installations');
        }

        $path = strtolower((string) parse_url((string) $this->url, PHP_URL_PATH));

        return match (true) {
            str_contains($path, '/chat') => 'chat',
            str_contains($path, '/tickets') => 'tickets',
            str_contains($path, '/technician-unavailability'), str_contains($path, '/technicians') => $role === 'admin' ? 'technicians' : 'dashboard',
            str_contains($path, '/installations') => $role === 'customer' ? 'applications' : 'installations',
            str_contains($path, '/applications'), str_contains($path, '/application'), str_contains($path, '/apply') => 'applications',
            str_contains($path, '/customers') => 'customers',
            default => null,
        };
    }

    public static function sidebarCountsFor(User $user): array
    {
        return $user->appNotifications()
            ->unread()
            ->get()
            ->reduce(function (array $counts, self $notification) use ($user): array {
                $section = $notification->sidebarSectionFor($user);

                if ($section !== null) {
                    $counts[$section] = ($counts[$section] ?? 0) + 1;
                }

                return $counts;
            }, []);
    }

    public static function markSidebarSectionRead(User $user, string $section): int
    {
        if (! in_array($section, self::SIDEBAR_SECTIONS, true)) {
            return 0;
        }

        $ids = $user->appNotifications()
            ->unread()
            ->get()
            ->filter(fn (self $notification) => $notification->sidebarSectionFor($user) === $section)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return self::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->update(['read_at' => now()]);
    }
}
