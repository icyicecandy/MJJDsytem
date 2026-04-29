<?php

namespace App\Models;

use App\Models\AppNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const TECHNICIAN_STANDARD_SHIFT = '8:00 AM - 5:00 PM, Mon - Fri';
    public const DISABLED_ACCOUNT_MESSAGE = 'This account has been disabled. Please contact an administrator.';
    public const CUSTOMER_ID_PREFIX = 'CUS';
    public const TECHNICIAN_ID_PREFIX = 'TECH';

    protected $fillable = [
        'name',
        'email',
        'customer_id',
        'technician_id',
        'google_id',
        'username',
        'bio',
        'phone',
        'phone_verified_at',
        'address',
        'technician_shift',
        'technician_status',
        'date_of_birth',
        'gender',
        'avatar_path',
        'language',
        'timezone',
        'theme_preference',
        'email_notifications',
        'push_notifications',
        'sms_notifications',
        'profile_visibility',
        'data_sharing_enabled',
        'deletion_requested_at',
        'disabled_at',
        'disabled_by',
        'password',
        'role',
        'agreed_to_terms',
        'agreed_to_privacy',
        'consented_at',
        'terms_version',
        'privacy_version',
        'is_online',
        'last_seen',
        'chat_available',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
        'initials',
    ];

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $updates = [];

            if ($user->isCustomer()
                && blank($user->customer_id)
                && Schema::hasColumn($user->getTable(), 'customer_id')) {
                $updates['customer_id'] = self::formatCustomerId((int) $user->id);
            }

            if ($user->isTechnician()
                && blank($user->technician_id)
                && Schema::hasColumn($user->getTable(), 'technician_id')) {
                $updates['technician_id'] = self::formatTechnicianId((int) $user->id);
            }

            if ($updates !== []) {
                $user->forceFill($updates)->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'agreed_to_terms' => 'boolean',
            'agreed_to_privacy' => 'boolean',
            'consented_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'last_seen' => 'datetime',
            'is_online' => 'boolean',
            'chat_available' => 'boolean',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'data_sharing_enabled' => 'boolean',
            'deletion_requested_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function getInitialsAttribute(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $segment) => Str::upper(Str::substr($segment, 0, 1)))
            ->implode('');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'technician_id');
    }

    public function installations()
    {
        return $this->hasMany(\App\Models\Installation::class, 'technician_id');
    }

    public function application()
    {
        return $this->hasOne(\App\Models\Application::class);
    }

    public function applications()
    {
        return $this->application();
    }

    public function latestApplication()
    {
        return $this->application();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTechnician(): bool
    {
        return $this->role === 'technician';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public static function formatCustomerId(int $id): string
    {
        return sprintf('%s-%06d', self::CUSTOMER_ID_PREFIX, $id);
    }

    public static function formatTechnicianId(int $id): string
    {
        return sprintf('%s-%06d', self::TECHNICIAN_ID_PREFIX, $id);
    }

    public function displayCustomerRecordId(): string
    {
        return $this->customer_id ?: self::formatCustomerId((int) $this->id);
    }

    public function displayTechnicianRecordId(): string
    {
        return $this->technician_id ?: self::formatTechnicianId((int) $this->id);
    }

    public function displayFirstName(): string
    {
        $segments = preg_split('/\s+/', trim((string) $this->name), 2);

        return $segments[0] ?? '';
    }

    public function displayLastName(): string
    {
        $segments = preg_split('/\s+/', trim((string) $this->name), 2);

        return $segments[1] ?? '';
    }

    public function displayTechnicianShift(): string
    {
        return self::TECHNICIAN_STANDARD_SHIFT;
    }

    public function ticketMessages()
    {
        return $this->hasMany(\App\Models\TicketMessage::class);
    }

    public function chatSent()
    {
        return $this->hasMany(\App\Models\ChatMessage::class, 'from_user_id');
    }

    public function chatReceived()
    {
        return $this->hasMany(\App\Models\ChatMessage::class, 'to_user_id');
    }

    public function supportChats()
    {
        return $this->hasMany(\App\Models\ChatMessage::class, 'customer_id');
    }

    public function assignedSupportChats()
    {
        return $this->hasMany(\App\Models\ChatMessage::class, 'assigned_staff_id');
    }

    public function installationLogs()
    {
        return $this->hasMany(\App\Models\InstallationLog::class);
    }

    public function disabledBy()
    {
        return $this->belongsTo(self::class, 'disabled_by');
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class)->latest();
    }

    public function scopeTechnicians($query)
    {
        return $query->where('role', 'technician');
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', 'customer');
    }

    public function scopeAvailableSupport($query)
    {
        return $query->whereIn('role', ['admin', 'technician'])
            ->where('is_online', true)
            ->where('chat_available', true);
    }

    public function chatPresenceThreshold(): Carbon
    {
        return now()->subSeconds((int) config('services.support_chat.presence_window_seconds', 120));
    }

    public function hasRecentChatActivity(): bool
    {
        return $this->last_seen !== null && $this->last_seen->gte($this->chatPresenceThreshold());
    }

    public function isEffectivelyChatAvailable(): bool
    {
        return ($this->isAdmin() || $this->isTechnician())
            && $this->is_online === true
            && $this->chat_available === true
            && $this->hasRecentChatActivity();
    }

    public function chatAvailabilityState(): string
    {
        if (! ($this->isAdmin() || $this->isTechnician())) {
            return 'offline';
        }

        if (! $this->is_online || ! $this->hasRecentChatActivity()) {
            return 'offline';
        }

        if ($this->chat_available) {
            return 'available';
        }

        if ($this->chat_available === false) {
            return 'away';
        }

        return 'offline';
    }

    public function chatAvailabilityLabel(): string
    {
        return match ($this->chatAvailabilityState()) {
            'available' => 'Available',
            'away' => 'Away',
            default => 'Offline',
        };
    }
}
