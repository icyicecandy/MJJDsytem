<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');

        $this->app->extend(UncompromisedVerifier::class, function ($verifier, $app) {
            // Windows PHP/OpenSSL may have no CA bundle. Use the existing OS trust
            // store for this service without changing other HTTP clients or TLS checks.
            if (PHP_OS_FAMILY !== 'Windows' || ! defined('CURLSSLOPT_NATIVE_CA')) {
                return $verifier;
            }

            $http = clone $app->make(HttpFactory::class);
            $http->globalOptions([
                'verify' => true,
                'curl' => [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA],
            ]);

            return new NotPwnedVerifier($http);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone', 'Asia/Manila'));

        Carbon::macro('inAppTimezone', function () {
            /** @var \Carbon\Carbon $this */
            return $this->copy()->timezone(config('app.timezone', 'Asia/Manila'));
        });

        Carbon::macro('displayDateTime', function (string $format = 'M d, Y h:i A') {
            /** @var \Carbon\Carbon $this */
            return $this->copy()->timezone(config('app.timezone', 'Asia/Manila'))->format($format);
        });

        Carbon::macro('displayTime', function (string $format = 'h:i A') {
            /** @var \Carbon\Carbon $this */
            return $this->copy()->timezone(config('app.timezone', 'Asia/Manila'))->format($format);
        });

        Carbon::macro('displayRelative', function () {
            /** @var \Carbon\Carbon $this */
            return $this->copy()->timezone(config('app.timezone', 'Asia/Manila'))->diffForHumans();
        });
    }
}
