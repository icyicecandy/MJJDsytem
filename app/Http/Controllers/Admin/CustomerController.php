<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $customers = User::query()
            ->customers()
            ->with('application')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('application', function ($applicationQuery) use ($search) {
                            $applicationQuery->where('reference_no', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile_number', 'like', "%{$search}%")
                                ->orWhere('plan_code', 'like', "%{$search}%")
                                ->orWhere('municipality', 'like', "%{$search}%")
                                ->orWhere('barangay', 'like', "%{$search}%")
                                ->orWhere('status', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                if ($status === 'without_application') {
                    $query->whereDoesntHave('application');

                    return;
                }

                $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('status', $status));
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'search', 'status'));
    }

    public function show(User $customer)
    {
        abort_unless($customer->isCustomer(), 404);

        $customer->loadCount([
            'tickets',
            'tickets as open_tickets_count' => fn ($query) => $query->whereIn('status', [
                Ticket::STATUS_OPEN,
                Ticket::STATUS_QUEUED,
                Ticket::STATUS_IN_PROGRESS,
            ]),
            'tickets as closed_tickets_count' => fn ($query) => $query->where('status', Ticket::STATUS_CLOSED),
        ]);

        $customer->load([
            'application.installation.technician',
            'tickets' => fn ($query) => $query
                ->with('technician')
                ->latest()
                ->take(10),
        ]);

        $application = $customer->application;
        $installation = $application?->installation;
        $latestTicket = $customer->tickets->first();

        return view('admin.customers.show', compact('customer', 'application', 'installation', 'latestTicket'));
    }
}
