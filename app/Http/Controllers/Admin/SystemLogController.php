<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Models\EmailDeliveryLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;

class SystemLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'view' => ['nullable', 'in:activity,email'],
            'search' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'in:application,installation,ticket,chat,account,availability,email,system'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'email_status' => ['nullable', 'in:pending,sent,failed'],
            'notification_type' => ['nullable', 'string', 'max:120'],
        ]);

        $selectedView = ($filters['view'] ?? 'activity') === 'email' ? 'email' : 'activity';

        $query = SystemLog::query()
            ->with('user')
            ->where('action', 'not like', '%Backup%')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('user_role', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['user_id'] ?? null, fn (Builder $query, int|string $userId) => $query->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['module'] ?? null, function (Builder $query, string $module): void {
                $query->where(function (Builder $moduleQuery) use ($module): void {
                    match ($module) {
                        'application' => $moduleQuery->where('action', 'like', '%Application%'),
                        'installation' => $moduleQuery->where('action', 'like', '%Installation%'),
                        'ticket' => $moduleQuery->where('action', 'like', '%Ticket%'),
                        'chat' => $moduleQuery->where('action', 'like', '%Chat%'),
                        'availability' => $moduleQuery->where('action', 'like', '%Unavailability%'),
                        'email' => $moduleQuery->where('action', 'like', '%Email%'),
                        'account' => $moduleQuery->where(function (Builder $accountQuery): void {
                            $accountQuery->where('action', 'like', '%Account%')
                                ->orWhere('action', 'like', '%Profile%')
                                ->orWhere('action', 'like', '%Password%');
                        }),
                        'system' => $moduleQuery->where('user_role', 'system'),
                    };
                });
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));


        $logs = (clone $query)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $users = User::query()
            ->whereIn('id', SystemLog::query()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $actions = SystemLog::query()
            ->whereNotNull('action')
            ->where('action', 'not like', '%Backup%')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $activeFilterCount = collect($filters)
            ->except('view')
            ->filter(fn ($value) => filled($value))
            ->count();

        $emailDeliveries = null;
        $emailTypes = collect();
        if (Schema::hasTable('email_delivery_logs')) {
            $emailQuery = EmailDeliveryLog::query()
                ->with('user')
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                    ->where(fn (Builder $nested) => $nested
                        ->where('recipient', 'like', "%{$search}%")
                        ->orWhere('notification_type', 'like', "%{$search}%")))
                ->when($filters['email_status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($filters['notification_type'] ?? null, fn (Builder $query, string $type) => $query->where('notification_type', $type))
                ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));

            $emailDeliveries = $emailQuery->latest()->paginate(25)->withQueryString();
            $emailTypes = EmailDeliveryLog::query()->distinct()->orderBy('notification_type')->pluck('notification_type');
        }

        return view('admin.system-logs.index', compact(
            'logs',
            'users',
            'actions',
            'filters',
            'activeFilterCount',
            'selectedView',
            'emailDeliveries',
            'emailTypes',
        ));
    }
}
