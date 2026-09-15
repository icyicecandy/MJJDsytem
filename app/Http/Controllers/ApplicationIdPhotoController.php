<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Support\ApplicationIdUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationIdPhotoController extends Controller
{
    public function __invoke(Request $request, Application $application, string $photo): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->isCustomer() && (int) $application->user_id === (int) $user->id), 403);
        $path = ApplicationIdUpload::path(match ($photo) {
            'id' => $application->id_image_path,
            'selfie' => $application->id_selfie_image_path,
            default => null,
        });
        abort_unless(ApplicationIdUpload::exists($path), 404);

        return Storage::disk(ApplicationIdUpload::disk($path))->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
