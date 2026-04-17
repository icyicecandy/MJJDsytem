<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\AppNotification;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'bio',
        'phone',
        'phone_verified_at',
        'date_of_birth',
        'gender',
        'address',
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'avatar_url',
        'initials',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'agreed_to_terms' => 'boolean',
            'agreed_to_privacy' => 'boolean',
            'consented_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'data_sharing_enabled' => 'boolean',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
            'last_seen' => 'datetime',
            'is_online' => 'boolean',
            'chat_available' => 'boolean',
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

    public function subscription()
    {
        return $this->hasOne(\App\Models\Subscription::class);
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
    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class)->latest();
    }

    public function scopeTechnicians($query)
    {
        return $query->where('role', 'technician');
    }

    public function scopeAvailableSupport($query)
    {
        return $query->whereIn('role', ['admin', 'technician'])
            ->where('chat_available', true);
    }
}
