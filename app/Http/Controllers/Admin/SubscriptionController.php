<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $query = Subscription::query()
            ->with(['user.application'])
            ->latest('created_at')
            ->latest('id');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user.application', function ($applicationQuery) use ($search) {
                        $applicationQuery->where('reference_no', 'like', "%{$search}%");
                    });
            });
        }

        $subscriptions = $query->paginate(15)->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions', 'status', 'search'));
    }
}
