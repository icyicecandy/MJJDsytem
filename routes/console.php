<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('applications:prune-drafts', function () {
    $this->info(App\Models\ApplicationDraft::pruneExpired().' expired drafts removed.');
})->purpose('Remove application drafts and their private photos after 30 days');

Illuminate\Support\Facades\Schedule::command('applications:prune-drafts')->daily()->withoutOverlapping();

Illuminate\Support\Facades\Schedule::command('tasks:alert-overdue')->everyFiveMinutes()->withoutOverlapping(10);

Illuminate\Support\Facades\Schedule::command('tickets:dispatch-queue')->everyFiveMinutes()->withoutOverlapping(10);
