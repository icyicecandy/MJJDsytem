<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TechnicianSeeder extends Seeder
{
    public function run(): void
    {
        $technicians = [
            [
                'name' => 'Juan Dela Cruz',
                'email' => 'juan.tech@mjjdtelecom.com',
                'phone' => '09171234567',
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'maria.tech@mjjdtelecom.com',
                'phone' => '09179876543',
            ],
        ];

        foreach ($technicians as $technician) {
            $user = User::updateOrCreate(
                ['email' => $technician['email']],
                [
                    'name' => $technician['name'],
                    'phone' => $technician['phone'],
                    'password' => Hash::make('password'),
                    'role' => 'technician',
                    'technician_shift' => User::TECHNICIAN_STANDARD_SHIFT,
                    'technician_status' => 'available',
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->technician_id) {
                $user->forceFill([
                    'technician_id' => User::formatTechnicianId((int) $user->id),
                ])->saveQuietly();
            }
        }
    }
}
