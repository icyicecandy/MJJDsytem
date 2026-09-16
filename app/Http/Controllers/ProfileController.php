<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\WorkflowTransaction;
use App\Support\ProfileSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'twoFactorAvailable' => false,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $obsoleteAvatar = WorkflowTransaction::run(function () use ($request) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $validated = $request->validated();
            $oldAvatar = $user->avatar_path;
            $emailChanged = $validated['email'] !== $user->email;
            $phoneChanged = ($validated['phone'] ?? null) !== $user->phone;

            if ($request->boolean('remove_avatar')) {
                $validated['avatar_path'] = null;
            }

            if ($request->hasFile('avatar')) {
                try {
                    $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
                } catch (Throwable $exception) {
                    report($exception);
                    $path = false;
                }
                if (! $path) {
                    throw ValidationException::withMessages([
                        'avatar' => 'Your photo could not be saved. Please try again.',
                    ]);
                }
                WorkflowTransaction::trackUpload($path, 'public');
                $validated['avatar_path'] = $path;
            }

            unset($validated['avatar'], $validated['remove_avatar']);
            $user->fill($validated);
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
            if ($phoneChanged) {
                $user->phone_verified_at = null;
            }
            $user->save();

            return $oldAvatar && $oldAvatar !== $user->avatar_path ? $oldAvatar : null;
        });

        // Only discard the old photo after its replacement/removal has saved.
        if ($obsoleteAvatar) {
            try {
                if (! Storage::disk('public')->delete($obsoleteAvatar)) {
                    Log::warning('Could not remove a replaced profile photo.', ['path' => $obsoleteAvatar]);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function export(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'bio' => $user->bio,
                'date_of_birth' => $user->date_of_birth?->toDateString(),
                'gender' => $user->gender,
                'language' => $user->language,
                'timezone' => $user->timezone,
                'theme_preference' => $user->theme_preference,
                'email_notifications' => (bool) $user->email_notifications,
                'push_notifications' => (bool) $user->push_notifications,
                'sms_notifications' => (bool) $user->sms_notifications,
                'profile_visibility' => $user->profile_visibility,
                'data_sharing_enabled' => (bool) $user->data_sharing_enabled,
                'deletion_requested_at' => $user->deletion_requested_at?->toDateTimeString(),
                'role' => $user->role,
            ],
        ]);
    }

    public function requestDeletionReview(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isAdmin(), 403);

        if (! $user->deletion_requested_at) {
            $user->forceFill([
                'deletion_requested_at' => now(),
            ])->save();
        }

        return Redirect::route('profile.edit')->with('status', 'deletion-requested');
    }

    public function usernameCheck(Request $request): JsonResponse
    {
        $candidate = ProfileSanitizer::username($request->string('username')->toString());

        if ($candidate === null) {
            return response()->json([
                'available' => false,
                'sanitized' => '',
                'message' => 'Enter a username to check availability.',
            ], 422);
        }

        $validator = validator(['username' => $candidate], [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique(User::class, 'username')->ignore($request->user()->id),
            ],
        ], [
            'username.unique' => 'That username is already taken. Please choose another one.',
            'username.regex' => 'Username may only contain lowercase letters, numbers, dots, hyphens, or underscores.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'sanitized' => $candidate,
                'message' => $validator->errors()->first('username'),
                'errors' => $validator->errors(),
            ], 422);
        }

        return response()->json([
            'available' => true,
            'sanitized' => $candidate,
            'message' => 'Username is available.',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
