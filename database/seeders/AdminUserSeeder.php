<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Device;
use App\Models\DeviceAuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('ADMIN_DEFAULT_EMAIL', 'admin@remotemonitor.local');
        $password = env('ADMIN_DEFAULT_PASSWORD', 'admin123456');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
