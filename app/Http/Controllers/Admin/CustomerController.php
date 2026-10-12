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
        $validatedFilters = $request->validate([
            'account_status' => ['nullable', 'in:active,disabled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'requires_action' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $accountStatus = $validatedFilters['account_status'] ?? '';
        $dateFrom = $validatedFilters['date_from'] ?? null;
        $dateTo = $validatedFilters['date_to'] ?? null;
        $requiresAction = $request->boolean('requires_action');

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
            ->when($accountStatus === 'active', fn ($query) => $query->whereNull('disabled_at'))
            ->when($accountStatus === 'disabled', fn ($query) => $query->whereNotNull('disabled_at'))
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($requiresAction, fn ($query) => $query->where(function ($attention) {
                $attention->whereNull('email_verified_at')->orWhereDoesntHave('application');
            }))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.customers.index', compact('customers', 'search', 'status', 'accountStatus', 'dateFrom', 'dateTo', 'requiresAction'));
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
            'disabledBy',
            'application.installation.technician',
            'tickets' => fn ($query) => $query
                ->with('assignedStaff')
                ->latest()
                ->take(10),
        ]);

        $application = $customer->application;
        $installation = $application?->installation;
        $latestTicket = $customer->tickets->first();

        return view('admin.customers.show', compact('customer', 'application', 'installation', 'latestTicket'));
    }

    public function disableAccount(Request $request, User $customer)
    {
        abort_unless($customer->isCustomer(), 404);

        $validated = $request->validate([
            'disabled_reason' => ['required', 'string', 'max:1000'],
        ], [
            'disabled_reason.required' => 'Please record why this customer account is being disabled.',
        ]);

        $customer->forceFill([
            'disabled_at' => $customer->disabled_at ?: now(),
            'disabled_by' => $request->user()->id,
            'disabled_reason' => trim($validated['disabled_reason']),
            'is_online' => false,
            'chat_available' => false,
            'last_seen' => null,
        ])->save();

        system_log(
            'Customer Account Disabled',
            $customer->displayCustomerRecordId().' was disabled. Reason: '.$customer->disabled_reason
        );

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'customer-disabled');
    }

    public function enableAccount(User $customer)
    {
        abort_unless($customer->isCustomer(), 404);

        $customerReference = $customer->displayCustomerRecordId();

        $customer->forceFill([
            'disabled_at' => null,
            'disabled_by' => null,
            'disabled_reason' => null,
        ])->save();

        system_log('Customer Account Enabled', $customerReference.' was enabled.');

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('status', 'customer-enabled');
    }
}
