<?php

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Carbon;

test('notification feed includes stable date grouping labels', function () {
    Carbon::setTestNow('2026-09-22 10:00:00');
    $user = User::factory()->create(['role' => 'customer']);

    $today = AppNotification::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Today notice',
        'message' => 'Current activity',
        'url' => route('dashboard'),
    ]);
    $yesterday = AppNotification::create([
        'user_id' => $user->id,
        'type' => 'test',
        'title' => 'Yesterday notice',
        'message' => 'Earlier activity',
        'url' => route('dashboard'),
    ]);
    $yesterday->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->saveQuietly();

    $response = $this->actingAs($user)->getJson(route('notifications.feed', absolute: false));

    $response->assertOk()
        ->assertJsonPath('all_notifications.0.id', $today->id)
        ->assertJsonPath('all_notifications.0.date_key', '2026-09-22')
        ->assertJsonPath('all_notifications.0.date_label', 'Today')
        ->assertJsonPath('all_notifications.1.id', $yesterday->id)
        ->assertJsonPath('all_notifications.1.date_key', '2026-09-21')
        ->assertJsonPath('all_notifications.1.date_label', 'Yesterday');
});
