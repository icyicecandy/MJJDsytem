<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TechnicianAccountCreatedMail;
use App\Models\Installation;
use App\Models\TechnicianUnavailabilityRequest;
use App\Models\User;
use App\Support\TrackedEmailSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TechnicianController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $employmentTypeOptions = User::technicianEmploymentTypeOptions();
        $workDayOptions = User::technicianWorkDayOptions();
        $temporaryPassword = old('temporary_password') ?: $this->generateTemporaryPassword();
        $pendingUnavailabilityCount = TechnicianUnavailabilityRequest::query()
            ->whereIn('status', [TechnicianUnavailabilityRequest::STATUS_PENDING, TechnicianUnavailabilityRequest::STATUS_CLARIFICATION])
            ->count();

        $technicians = User::query()
            ->technicians()
            ->withCount([
                'installations',
                'installations as scheduled_installations_count' => fn ($query) => $query->where('status', 'scheduled'),
                'installations as completed_installations_count' => fn ($query) => $query->whereIn('status', Installation::completedStatuses()),
                'assignedTickets as open_tickets_count' => fn ($query) => $query->whereIn('status', ['open', 'queued', 'in_progress']),
            ])
            ->with([
                'installations' => fn ($query) => $query
                    ->with('application')
                    ->where('status', 'scheduled')
                    ->orderBy('installation_date')
                    ->orderBy('installation_time'),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('technician_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('technician_shift', 'like', "%{$search}%")
                        ->orWhereRaw("CASE WHEN disabled_at IS NULL THEN 'active' ELSE 'disabled' END LIKE ?", ["%{$search}%"])
                        ->orWhere('technician_status', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                if ($status === 'active') {
                    $query->whereNull('disabled_at');

                    return;
                }

                if ($status === 'disabled') {
                    $query->whereNotNull('disabled_at');
                }
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.technicians.index', compact(
            'technicians',
            'search',
            'status',
            'employmentTypeOptions',
            'workDayOptions',
            'temporaryPassword',
            'pendingUnavailabilityCount'
        ));
    }

    public function show(User $technician)
    {
        abort_unless($technician->isTechnician(), 404);

        $technician->loadCount([
            'installations',
            'installations as scheduled_installations_count' => fn ($query) => $query->where('status', 'scheduled'),
            'installations as completed_installations_count' => fn ($query) => $query->whereIn('status', Installation::completedStatuses()),
            'assignedTickets as open_tickets_count' => fn ($query) => $query->whereIn('status', ['open', 'queued', 'in_progress']),
        ]);

        $technician->load([
            'installations' => fn ($query) => $query
                ->with('application.user')
                ->orderByRaw("CASE WHEN status = 'scheduled' THEN 0 WHEN status = 'completed' THEN 1 WHEN status = 'activated' THEN 2 ELSE 3 END")
                ->orderBy('installation_date')
                ->orderBy('installation_time'),
            'assignedTickets' => fn ($query) => $query
                ->with('user')
                ->latest()
                ->take(10),
            'disabledBy',
        ]);

        $upcomingInstallation = $technician->installations
            ->first(fn ($installation) => $installation->status === 'scheduled');

        return view('admin.technicians.show', compact('technician', 'upcomingInstallation'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}][\p{L}\p{M}\s\'.-]*$/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}][\p{L}\p{M}\s\'.-]*$/u'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'address' => ['required', 'string', 'max:255'],
            'technician_employment_type' => ['required', Rule::in(array_keys(User::technicianEmploymentTypeOptions()))],
            'part_time_days' => ['required_if:technician_employment_type,part_time', 'array', 'min:1', 'max:3'],
            'part_time_days.*' => ['string', 'distinct', Rule::in(array_keys(User::technicianWorkDayOptions()))],
            'technician_shift_start' => ['nullable', 'date_format:H:i'],
            'technician_shift_end' => ['nullable', 'date_format:H:i', 'after:technician_shift_start'],
            'technician_break_start' => ['nullable', 'date_format:H:i', 'required_with:technician_break_end'],
            'technician_break_end' => ['nullable', 'date_format:H:i', 'required_with:technician_break_start', 'after:technician_break_start'],
            'technician_unavailable_dates' => ['nullable', 'array'],
            'technician_unavailable_dates.*' => ['nullable', 'date_format:Y-m-d', 'distinct'],
            'technician_max_active_tickets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'temporary_password' => [
                'required',
                'string',
                'max:64',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'first_name.regex' => 'First name may contain letters, spaces, apostrophes, periods, and hyphens.',
            'last_name.regex' => 'Last name may contain letters, spaces, apostrophes, periods, and hyphens.',
            'phone.regex' => 'Enter a valid contact number with 10 to 15 digits.',
            'part_time_days.required_if' => 'Select at least one part-time work day.',
            'part_time_days.min' => 'Select at least one part-time work day.',
            'part_time_days.max' => 'Select up to three part-time work days.',
        ]);

        $technicianShift = $validated['technician_employment_type'] === 'part_time'
            ? User::formatPartTimeShift($validated['part_time_days'])
            : User::TECHNICIAN_STANDARD_SHIFT;

        $technician = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'technician_shift' => $technicianShift,
            'technician_shift_start' => $validated['technician_shift_start'] ?? '08:00',
            'technician_shift_end' => $validated['technician_shift_end'] ?? '17:00',
            'technician_break_start' => $validated['technician_break_start'] ?? null,
            'technician_break_end' => $validated['technician_break_end'] ?? null,
            'technician_work_days' => $validated['technician_employment_type'] === 'part_time'
                ? array_values($validated['part_time_days'])
                : array_keys(User::technicianWorkDayOptions()),
            'technician_unavailable_dates' => array_values(array_filter($validated['technician_unavailable_dates'] ?? [])),
            'technician_max_active_tickets' => $validated['technician_max_active_tickets']
                ?? config('ticket-routing.max_active_tickets_per_technician', 5),
            'technician_employment_type' => $validated['technician_employment_type'],
            'technician_status' => 'available',
            'role' => 'technician',
            'password' => $validated['temporary_password'],
            'password_set_at' => null,
            'must_change_password' => true,
            'email_verified_at' => now(),
            'is_online' => false,
            'chat_available' => false,
        ]);

        $keyEmailSent = TrackedEmailSender::attempt(
            $technician->email,
            'Technician account invitation',
            fn () => Mail::to($technician->email)->send(
                new TechnicianAccountCreatedMail($technician, $validated['temporary_password'])
            ),
            ['technician_id' => $technician->id],
            $technician->id
        );

        return redirect()
            ->route('admin.technicians.show', $technician)
            ->with('success', 'Technician account created. Temporary key: '.$validated['temporary_password'].($keyEmailSent
                ? ' Account key email sent to '.$technician->email.'.'
                : ' Account key email could not be sent. Share this key manually.'));
    }

    public function updateSchedule(Request $request, User $technician): RedirectResponse
    {
        abort_unless($technician->isTechnician(), 404);

        $validated = $request->validate([
            'technician_work_days' => ['required', 'array', 'min:1', 'max:5'],
            'technician_work_days.*' => ['string', 'distinct', Rule::in(array_keys(User::technicianWorkDayOptions()))],
            'technician_shift_start' => ['required', 'date_format:H:i'],
            'technician_shift_end' => ['required', 'date_format:H:i', 'after:technician_shift_start'],
            'technician_break_start' => ['nullable', 'date_format:H:i', 'required_with:technician_break_end'],
            'technician_break_end' => ['nullable', 'date_format:H:i', 'required_with:technician_break_start', 'after:technician_break_start'],
            'technician_unavailable_dates' => ['nullable', 'array'],
            'technician_unavailable_dates.*' => ['nullable', 'date_format:Y-m-d', 'distinct'],
            'technician_status' => ['required', Rule::in(['available', 'off_duty', 'on_leave'])],
            'technician_max_active_tickets' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        if (filled($validated['technician_break_start'] ?? null)
            && ($validated['technician_break_start'] < $validated['technician_shift_start']
                || $validated['technician_break_end'] > $validated['technician_shift_end'])) {
            return back()->withErrors([
                'technician_break_start' => 'The break period must be inside the technician shift.',
            ])->withInput();
        }

        $days = array_values($validated['technician_work_days']);
        $technician->forceFill([
            'technician_work_days' => $days,
            'technician_shift_start' => $validated['technician_shift_start'],
            'technician_shift_end' => $validated['technician_shift_end'],
            'technician_break_start' => $validated['technician_break_start'] ?? null,
            'technician_break_end' => $validated['technician_break_end'] ?? null,
            'technician_unavailable_dates' => array_values(array_filter($validated['technician_unavailable_dates'] ?? [])),
            'technician_max_active_tickets' => $validated['technician_max_active_tickets'],
            'technician_status' => $validated['technician_status'],
            'technician_shift' => User::TECHNICIAN_WORKING_HOURS.', '.collect($days)
                ->map(fn (string $day) => User::TECHNICIAN_WORK_DAYS[$day])
                ->implode(' / '),
        ])->save();

        return back()->with('success', 'Technician schedule and workload settings updated.');
    }

    public function disableAccount(Request $request, User $technician): RedirectResponse
    {
        abort_unless($technician->isTechnician(), 404);

        $validated = $request->validate([
            'disabled_reason' => ['required', 'string', 'max:1000'],
        ], [
            'disabled_reason.required' => 'Please record why this technician account is being disabled.',
        ]);

        $technician->forceFill([
            'disabled_at' => $technician->disabled_at ?: now(),
            'disabled_by' => $request->user()->id,
            'disabled_reason' => trim($validated['disabled_reason']),
            'is_online' => false,
            'chat_available' => false,
            'last_seen' => null,
            'technician_status' => 'off_duty',
            'remember_token' => Str::random(60),
        ])->save();

        $technician->trustedDevices()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        system_log(
            'Technician Account Disabled',
            $technician->displayTechnicianRecordId().' was disabled. Reason: '.$technician->disabled_reason
        );

        return redirect()
            ->route('admin.technicians.show', $technician)
            ->with('status', 'technician-disabled');
    }

    public function enableAccount(User $technician): RedirectResponse
    {
        abort_unless($technician->isTechnician(), 404);

        $technicianReference = $technician->displayTechnicianRecordId();

        $technician->forceFill([
            'disabled_at' => null,
            'disabled_by' => null,
            'disabled_reason' => null,
            'technician_status' => 'available',
        ])->save();

        system_log('Technician Account Enabled', $technicianReference.' was enabled.');

        return redirect()
            ->route('admin.technicians.show', $technician)
            ->with('status', 'technician-enabled');
    }

    private function generateTemporaryPassword(): string
    {
        $sets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '!@#$%&*',
        ];

        $password = '';
        foreach ($sets as $set) {
            $password .= $set[random_int(0, strlen($set) - 1)];
        }

        $pool = implode('', $sets);
        while (strlen($password) < 14) {
            $password .= $pool[random_int(0, strlen($pool) - 1)];
        }

        return str_shuffle($password);
    }
}
