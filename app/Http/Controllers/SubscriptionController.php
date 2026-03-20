<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function show()
    {
        $user = Auth::user()->load('application.installation');
        $subscription = Subscription::where('user_id', $user->id)->first();
        $installation = optional($user->application)->installation;

        return view('customer.subscription', compact('subscription', 'installation'));
    }
}
