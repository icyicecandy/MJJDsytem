<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifyEmailController extends Controller
{
    /**
     * Verify the customer identified by a valid signed email link.
     */
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse|View
    {
        $user = User::query()->findOrFail($id);

        abort_unless($user->isCustomer() && ! $user->isDisabled(), 403);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));

            SystemLog::create([
                'user_id' => $user->id,
                'user_role' => $user->role,
                'action' => 'Customer Email Verified',
                'description' => 'Confirmed email address '.$user->email.'.',
            ]);
        }

        if ($request->user()?->is($user)) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        return view('auth.email-verified', [
            'verifiedEmail' => $user->email,
            'currentUser' => $request->user(),
        ]);
    }
}
