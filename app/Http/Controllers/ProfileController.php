<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Support\ProfileSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'languageOptions' => [
                'en' => 'English',
                'fil' => 'Filipino',
            ],
            'themeOptions' => [
                'system' => 'System',
                'light' => 'Light',
                'dark' => 'Dark',
            ],
            'visibilityOptions' => [
                'private' => 'Private',
                'public' => 'Public',
            ],
            'genderOptions' => [
                'male' => 'Male',
                'female' => 'Female',
                'non_binary' => 'Non-binary',
                'prefer_not_to_say' => 'Prefer not to say',
            ],
            'timezones' => timezone_identifiers_list(),
            'twoFactorAvailable' => false,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $emailChanged = $validated['email'] !== $user->email;
        $phoneChanged = ($validated['phone'] ?? null) !== $user->phone;

        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $validated['avatar_path'] = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $validated['avatar_path'] = $request->file('avatar')->store('avatars/' . $user->id, 'public');
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

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
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

    public function export(Request $request)
    {
        $user = $request->user()->loadMissing(['application', 'tickets', 'installations']);

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'phone' => $user->phone,
                'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                'bio' => $user->bio,
                'date_of_birth' => $user->date_of_birth?->toDateString(),
                'gender' => $user->gender,
                'address' => $user->address,
                'language' => $user->language,
                'timezone' => $user->timezone,
                'theme_preference' => $user->theme_preference,
                'email_notifications' => $user->email_notifications,
                'push_notifications' => $user->push_notifications,
                'sms_notifications' => $user->sms_notifications,
                'profile_visibility' => $user->profile_visibility,
                'data_sharing_enabled' => $user->data_sharing_enabled,
                'role' => $user->role,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'application' => $user->application,
            'tickets' => $user->tickets,
            'installations' => $user->installations,
        ];

        if (Schema::hasTable('subscriptions')) {
            $payload['subscription'] = $user->subscription;
        }

        $filename = 'account-export-' . now()->format('Ymd-His') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->deletion_requested_at) {
            $user->forceFill([
                'deletion_requested_at' => now(),
            ])->save();
        }

        return Redirect::route('profile.edit')->with('status', 'deletion-requested');
    }

    public function destroy(Request $request): RedirectResponse
    {
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
