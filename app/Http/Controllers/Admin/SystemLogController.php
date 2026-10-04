<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'in:application,installation,ticket,chat,account,system'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = SystemLog::query()
            ->with('user')
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
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $activeFilterCount = collect($filters)
            ->filter(fn ($value) => filled($value))
            ->count();

        return view('admin.system-logs.index', compact(
            'logs',
            'users',
            'actions',
            'filters',
            'activeFilterCount',
        ));
    }
}
