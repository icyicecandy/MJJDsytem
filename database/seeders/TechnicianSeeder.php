<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Technician;

class TechnicianSeeder extends Seeder
{
    public function run(): void
    {
        Technician::insert([
            [
                'name' => 'Juan Dela Cruz',
                'email' => 'juan.tech@mjjdtelecom.com',
                'mobile_number' => '09171234567',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'maria.tech@mjjdtelecom.com',
                'mobile_number' => '09179876543',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
