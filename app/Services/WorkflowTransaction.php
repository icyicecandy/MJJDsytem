<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Installation;
use App\Models\Ticket;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Coordinates writes using fresh, locked records instead of route-binding snapshots. */
class WorkflowTransaction
{
    private static array $uploadScopes = [];

    public static function run(Closure $action): mixed
    {
        $scope = count(self::$uploadScopes);
        self::$uploadScopes[] = [];

        try {
            return DB::transaction(function () use ($action, $scope) {
                // Clean up uploads from an abandoned deadlock attempt before retrying.
                self::discardUploads($scope);

                if (DB::connection()->getDriverName() === 'sqlite') {
                    // SQLite has no SELECT FOR UPDATE. Reserve its writer before any
                    // reads, without changing data, so another writer waits first.
                    DB::table('users')->whereRaw('1 = 0')->update(['id' => DB::raw('id')]);
                }

                return $action();
            }, 3);
        } catch (Throwable $exception) {
            self::discardUploads($scope);
            throw $exception;
        } finally {
            array_pop(self::$uploadScopes);
        }
    }

    public static function trackUpload(string $path, string $disk = 'public'): void
    {
        if (self::$uploadScopes !== []) {
            self::$uploadScopes[array_key_last(self::$uploadScopes)][] = [$disk, $path];
        }
    }

    private static function discardUploads(int $scope): void
    {
        foreach (self::$uploadScopes[$scope] as [$disk, $path]) {
            try {
                if (! Storage::disk($disk)->delete($path)) {
                    Log::warning('Could not remove a rolled-back upload.', ['disk' => $disk, 'path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Could not remove a rolled-back upload.', [
                    'disk' => $disk, 'path' => $path, 'error' => $exception->getMessage(),
                ]);
            }
        }
        self::$uploadScopes[$scope] = [];
    }

    public static function application(Application $application, Closure $action, ?int $technicianId = null): mixed
    {
        return self::run(function () use ($application, $action, $technicianId) {
            self::lockTechnician($technicianId);
            $current = Application::query()->lockForUpdate()->findOrFail($application->id);
            self::loadInstallation($current);

            return $action($current);
        });
    }

    public static function installation(Installation $installation, Closure $action, ?int $technicianId = null): mixed
    {
        return self::run(function () use ($installation, $action, $technicianId) {
            self::lockTechnician($technicianId);
            // All installation actions lock their parent first, including cancellation.
            $application = Application::query()->lockForUpdate()->findOrFail($installation->application_id);
            $current = Installation::query()->lockForUpdate()->findOrFail($installation->id);
            $current->setRelation('application', $application);

            return $action($current);
        });
    }

    public static function customerApplication(User $customer, Closure $action): mixed
    {
        return self::run(function () use ($customer, $action) {
            // Protect the first submission even when no application row exists yet.
            $current = User::query()->lockForUpdate()->findOrFail($customer->id);
            $application = $current->application()->lockForUpdate()->first();
            if ($application) {
                self::loadInstallation($application);
            }
            $current->setRelation('application', $application);

            return $action($current);
        });
    }

    public static function ticket(Ticket $ticket, Closure $action): mixed
    {
        return self::run(fn () => $action(
            Ticket::query()->lockForUpdate()->findOrFail($ticket->id)
        ));
    }

    private static function lockTechnician(?int $technicianId): void
    {
        if ($technicianId) {
            // An empty calendar still needs a stable row lock to serialize bookings.
            User::query()->whereKey($technicianId)->lockForUpdate()->first();
        }
    }

    private static function loadInstallation(Application $application): void
    {
        $application->setRelation('installation', $application->installation()->lockForUpdate()->first());
    }
}
