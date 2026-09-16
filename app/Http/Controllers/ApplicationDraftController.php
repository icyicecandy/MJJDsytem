<?php

namespace App\Http\Controllers;

use App\Models\ApplicationDraft;
use App\Models\User;
use App\Services\WorkflowTransaction;
use App\Support\ApplicationIdUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicationDraftController extends Controller
{
    public function store(Request $request)
    {
        // Drafts allow incomplete text; the final application retains strict validation.
        $rules = array_fill_keys(ApplicationDraft::FIELDS, ['nullable', 'string', 'max:255']);
        foreach (['first_name', 'last_name', 'region', 'province', 'municipality', 'barangay'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:100'];
        }
        $values = $request->validate($rules + [
            'draft_token' => ['nullable', 'uuid'], 'draft_step' => ['required', 'integer', 'between:1,5'],
            'id_selfie_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        [$draft, $obsolete] = WorkflowTransaction::customerApplication($request->user(), function (User $user) use ($request, $values) {
            abort_if($user->application && ! $user->application->isTerminalForNewApplication(), 409, 'You already have an active application.');
            $row = ApplicationDraft::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $current = $row && $row->expires_at->isFuture() ? $row : null;
            abort_if(($values['draft_token'] ?? null) !== $current?->token, 409, 'This draft changed in another tab or expired. Reload Apply before saving again.');
            $oldPhoto = $row?->photo_path;
            $photo = $current?->photo_path;
            if ($request->hasFile('id_selfie_image')) $photo = ApplicationIdUpload::store($request->file('id_selfie_image'));
            $payload = [];
            foreach (ApplicationDraft::FIELDS as $field) $payload[$field] = $values[$field] ?? null;
            $draft = $row ?? new ApplicationDraft(['user_id' => $user->id]);
            $draft->fill([
                'payload' => $payload, 'step' => $values['draft_step'], 'token' => (string) Str::uuid(),
                'photo_path' => $photo, 'expires_at' => now()->addDays(30),
            ])->save();
            return [$draft, $oldPhoto !== $photo ? $oldPhoto : null];
        });
        ApplicationDraft::deletePhoto($obsolete);
        return response()->json([
            'token' => $draft->token, 'saved_at' => $draft->updated_at->toIso8601String(),
            'expires_at' => $draft->expires_at->toIso8601String(), 'photo_url' => $draft->photoUrl(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request)
    {
        $values = $request->validate(['draft_token' => ['required', 'uuid']]);
        $photo = WorkflowTransaction::customerApplication($request->user(), function (User $user) use ($values) {
            $draft = ApplicationDraft::query()->where('user_id', $user->id)->lockForUpdate()->first();
            abort_if(! $draft || $draft->token !== $values['draft_token'], 409, 'This draft changed in another tab. Reload Apply before discarding it.');
            $photo = $draft->photo_path;
            $draft->delete();
            return $photo;
        });
        ApplicationDraft::deletePhoto($photo);
        return response()->noContent();
    }

    public function photo(Request $request)
    {
        $draft = ApplicationDraft::query()->where('user_id', $request->user()->id)->where('expires_at', '>', now())->first();
        abort_unless($draft && $draft->hasPhoto(), 404);
        return Storage::disk('local')->response($draft->photo_path, 'draft-id-photo.'.pathinfo($draft->photo_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
