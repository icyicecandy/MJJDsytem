<?php

namespace App\Models;

use App\Notifications\VerifyCustomerEmail;
use App\Support\TrackedEmailSender;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, Notifiable;

    public function sendEmailVerificationNotification(): void
    {
        TrackedEmailSender::attempt(
            $this->email,
            'Customer email verification',
            fn () => $this->notify(new VerifyCustomerEmail),
            ['user_id' => $this->id],
            $this->id,
            true
        );
    }

    public const TECHNICIAN_STANDARD_SHIFT = '8:00 AM - 5:00 PM, Mon - Fri';

    public const TECHNICIAN_WORKING_HOURS = '8:00 AM - 5:00 PM';

    public const TECHNICIAN_SHIFT_OPTIONS = [
        '8:00 AM - 5:00 PM, Mon - Fri' => '8:00 AM - 5:00 PM, Mon - Fri',
        '9:00 AM - 6:00 PM, Mon - Fri' => '9:00 AM - 6:00 PM, Mon - Fri',
        '10:00 AM - 7:00 PM, Mon - Fri' => '10:00 AM - 7:00 PM, Mon - Fri',
        'Rotating / On-call' => 'Rotating / On-call',
    ];

    public const TECHNICIAN_WORK_DAYS = [
        'mon' => 'Mon',
        'tue' => 'Tue',
        'wed' => 'Wed',
        'thu' => 'Thu',
        'fri' => 'Fri',
    ];

    public const TECHNICIAN_EMPLOYMENT_TYPES = [
        'full_time' => 'Full Time',
        'part_time' => 'Part Time',
    ];

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
        'technician_shift_start',
        'technician_shift_end',
        'technician_break_start',
        'technician_break_end',
        'technician_work_days',
        'technician_unavailable_dates',
        'technician_max_active_tickets',
        'technician_employment_type',
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
        'disabled_reason',
        'password',
        'password_set_at',
        'must_change_password',
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
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'technician_work_days' => 'array',
            'technician_unavailable_dates' => 'array',
            'technician_max_active_tickets' => 'integer',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'data_sharing_enabled' => 'boolean',
            'deletion_requested_at' => 'datetime',
            'disabled_at' => 'datetime',
            'password_set_at' => 'datetime',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
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
        return $this->hasMany(Ticket::class, 'assigned_staff_id');
    }

    public function installations()
    {
        return $this->hasMany(Installation::class, 'technician_id');
    }

    public function application()
    {
        return $this->hasOne(Application::class)->latestOfMany();
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
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

    public function canSetInitialPassword(): bool
    {
        return filled($this->google_id) && $this->password_set_at === null;
    }

    public function requiresPasswordChange(): bool
    {
        return $this->isTechnician() && (bool) $this->must_change_password;
    }

    public function requiresStaffTwoFactor(): bool
    {
        return $this->isAdmin() || $this->isTechnician();
    }

    public function hasStaffTwoFactorEnabled(): bool
    {
        return $this->requiresStaffTwoFactor()
            && filled($this->two_factor_secret)
            && $this->two_factor_confirmed_at !== null;
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
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
        return $this->technician_shift ?: self::TECHNICIAN_STANDARD_SHIFT;
    }

    public function technicianWorkDays(): array
    {
        $storedDays = collect($this->technician_work_days ?? [])
            ->filter(fn ($day) => is_string($day) && array_key_exists($day, self::TECHNICIAN_WORK_DAYS))
            ->unique()
            ->values()
            ->all();

        if ($storedDays !== []) {
            return $storedDays;
        }

        if ($this->technician_employment_type === 'part_time' && filled($this->technician_shift)) {
            $dayLabels = collect(self::TECHNICIAN_WORK_DAYS)->flip();
            $scheduleDays = str_contains($this->technician_shift, ',')
                ? trim(substr($this->technician_shift, strpos($this->technician_shift, ',') + 1))
                : '';

            $parsedDays = collect(preg_split('/\s*\/\s*/', $scheduleDays, -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn (string $label) => $dayLabels->get(trim($label)))
                ->filter()
                ->values()
                ->all();

            if ($parsedDays !== []) {
                return $parsedDays;
            }
        }

        return array_keys(self::TECHNICIAN_WORK_DAYS);
    }

    public function technicianShiftStart(): string
    {
        return $this->technician_shift_start
            ? \Carbon\Carbon::parse($this->technician_shift_start)->format('H:i')
            : '08:00';
    }

    public function technicianShiftEnd(): string
    {
        return $this->technician_shift_end
            ? \Carbon\Carbon::parse($this->technician_shift_end)->format('H:i')
            : '17:00';
    }

    public function worksOnDate(CarbonInterface|string $date): bool
    {
        $calendarDate = \Carbon\Carbon::parse($date);
        $dayKey = strtolower($calendarDate->format('D'));
        $unavailableDates = collect($this->technician_unavailable_dates ?? [])
            ->filter(fn ($value) => is_string($value))
            ->map(fn (string $value) => \Carbon\Carbon::parse($value)->toDateString());

        return in_array($dayKey, $this->technicianWorkDays(), true)
            && ! $unavailableDates->contains($calendarDate->toDateString());
    }

    public function technicianBreakStart(): ?string
    {
        return $this->technician_break_start
            ? \Carbon\Carbon::parse($this->technician_break_start)->format('H:i')
            : null;
    }

    public function technicianBreakEnd(): ?string
    {
        return $this->technician_break_end
            ? \Carbon\Carbon::parse($this->technician_break_end)->format('H:i')
            : null;
    }

    public function maximumActiveTicketWorkload(): int
    {
        return max(1, (int) ($this->technician_max_active_tickets
            ?: config('ticket-routing.max_active_tickets_per_technician', 5)));
    }

    public function displayTechnicianEmploymentType(): string
    {
        return self::TECHNICIAN_EMPLOYMENT_TYPES[$this->technician_employment_type] ?? 'Not set';
    }

    public static function technicianShiftOptions(): array
    {
        return self::TECHNICIAN_SHIFT_OPTIONS;
    }

    public static function technicianEmploymentTypeOptions(): array
    {
        return self::TECHNICIAN_EMPLOYMENT_TYPES;
    }

    public static function technicianWorkDayOptions(): array
    {
        return self::TECHNICIAN_WORK_DAYS;
    }

    public static function formatPartTimeShift(array $days): string
    {
        $labels = collect($days)
            ->map(fn (string $day) => self::TECHNICIAN_WORK_DAYS[$day] ?? null)
            ->filter()
            ->values()
            ->implode(' / ');

        return self::TECHNICIAN_WORKING_HOURS.', '.$labels;
    }

    public function ticketMessages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function chatSent()
    {
        return $this->hasMany(ChatMessage::class, 'from_user_id');
    }

    public function chatReceived()
    {
        return $this->hasMany(ChatMessage::class, 'to_user_id');
    }

    public function supportChats()
    {
        return $this->hasMany(ChatMessage::class, 'customer_id');
    }

    public function assignedSupportChats()
    {
        return $this->hasMany(ChatMessage::class, 'assigned_staff_id');
    }

    public function installationLogs()
    {
        return $this->hasMany(InstallationLog::class);
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
