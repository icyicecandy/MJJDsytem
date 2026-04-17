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
            User::updateOrCreate(
                ['email' => $technician['email']],
                [
                    'name' => $technician['name'],
                    'phone' => $technician['phone'],
                    'password' => Hash::make('password'),
                    'role' => 'technician',
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
