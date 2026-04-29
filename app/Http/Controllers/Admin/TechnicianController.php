<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TechnicianAccountCreatedMail;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class TechnicianController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $employmentTypeOptions = User::technicianEmploymentTypeOptions();
        $workDayOptions = User::technicianWorkDayOptions();
        $temporaryPassword = old('temporary_password') ?: $this->generateTemporaryPassword();

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
            'temporaryPassword'
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
            'first_name' => ['required', 'string', 'max:100', "regex:/^[A-Za-z][A-Za-z\\s'\\-]*$/"],
            'last_name' => ['required', 'string', 'max:100', "regex:/^[A-Za-z][A-Za-z\\s'\\-]*$/"],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'address' => ['required', 'string', 'max:255'],
            'technician_employment_type' => ['required', Rule::in(array_keys(User::technicianEmploymentTypeOptions()))],
            'part_time_days' => ['required_if:technician_employment_type,part_time', 'array', 'min:1', 'max:3'],
            'part_time_days.*' => ['string', 'distinct', Rule::in(array_keys(User::technicianWorkDayOptions()))],
            'temporary_password' => ['required', 'string', 'min:12', 'max:64'],
        ], [
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes.',
            'phone.regex' => 'Enter a valid contact number with 10 to 15 digits.',
            'part_time_days.required_if' => 'Select at least one part-time work day.',
            'part_time_days.min' => 'Select at least one part-time work day.',
            'part_time_days.max' => 'Select up to three part-time work days.',
        ]);

        $technicianShift = $validated['technician_employment_type'] === 'part_time'
            ? User::formatPartTimeShift($validated['part_time_days'])
            : User::TECHNICIAN_STANDARD_SHIFT;

        $technician = User::create([
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'technician_shift' => $technicianShift,
            'technician_employment_type' => $validated['technician_employment_type'],
            'technician_status' => 'available',
            'role' => 'technician',
            'password' => $validated['temporary_password'],
            'password_set_at' => now(),
            'email_verified_at' => now(),
            'is_online' => false,
            'chat_available' => false,
        ]);

        $keyEmailSent = true;

        try {
            Mail::to($technician->email)->send(
                new TechnicianAccountCreatedMail($technician, $validated['temporary_password'])
            );
        } catch (Throwable $exception) {
            $keyEmailSent = false;

            Log::warning('Technician account key email failed to send.', [
                'technician_id' => $technician->id,
                'email' => $technician->email,
                'error' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.technicians.show', $technician)
            ->with('success', 'Technician account created. Temporary key: ' . $validated['temporary_password'] . ($keyEmailSent
                ? ' Account key email sent to ' . $technician->email . '.'
                : ' Account key email could not be sent. Share this key manually.'));
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
        ])->save();

        return redirect()
            ->route('admin.technicians.show', $technician)
            ->with('status', 'technician-disabled');
    }

    public function enableAccount(User $technician): RedirectResponse
    {
        abort_unless($technician->isTechnician(), 404);

        $technician->forceFill([
            'disabled_at' => null,
            'disabled_by' => null,
            'disabled_reason' => null,
            'technician_status' => 'available',
        ])->save();

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
