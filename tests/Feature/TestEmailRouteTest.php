<?php

use App\Models\User;

test('test email route is not publicly accessible', function () {
    $this->get('/test-email')->assertNotFound();
});

test('test email route is not accessible even to authenticated admins', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/test-email')
        ->assertNotFound();
});
