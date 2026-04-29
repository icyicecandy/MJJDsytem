<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');
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
