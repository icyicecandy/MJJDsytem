<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Installation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

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

        return view('admin.technicians.index', compact('technicians', 'search', 'status'));
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

    public function disableAccount(Request $request, User $technician): RedirectResponse
    {
        abort_unless($technician->isTechnician(), 404);

        $technician->forceFill([
            'disabled_at' => $technician->disabled_at ?: now(),
            'disabled_by' => $request->user()->id,
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
            'technician_status' => 'available',
        ])->save();

        return redirect()
            ->route('admin.technicians.show', $technician)
            ->with('status', 'technician-enabled');
    }
}
