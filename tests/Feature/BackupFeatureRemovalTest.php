<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('application managed backup and recovery surfaces are fully removed', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    expect(Route::has('admin.backups.index'))->toBeFalse()
        ->and(Route::has('admin.backups.store'))->toBeFalse()
        ->and(Route::has('admin.backups.authorize-recovery'))->toBeFalse()
        ->and(Artisan::all())->not->toHaveKey('system:backup')
        ->and(Artisan::all())->not->toHaveKey('system:restore')
        ->and(config('filesystems.disks'))->not->toHaveKey('backup')
        ->and(Schema::hasTable('backup_records'))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.dashboard', absolute: false))
        ->assertOk()
        ->assertDontSee('Backup &amp; Recovery', false)
        ->assertSee('Analytics');
});
