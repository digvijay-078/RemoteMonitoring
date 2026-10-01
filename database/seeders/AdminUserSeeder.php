<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Device;
use App\Models\Monitor;
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

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]
        );

        // Seed an initial sample device for Phase 1 testing
        if (Device::count() === 0) {
            $device = Device::create([
                'device_identifier' => 'TAB-001',
                'name' => 'ICU Central Display',
                'location' => 'Building A, Floor 3',
                'status' => 'pending_pair',
                'view_mode' => 'single',
                'cycle_interval_seconds' => 30,
            ]);

            Monitor::create([
                'device_id' => $device->id,
                'slot_number' => 1,
                'name' => 'Monitor 01 - Vital Signs',
                'config_version' => 1,
                'is_enabled' => true,
            ]);

            DeviceAuditLog::create([
                'device_id' => $device->id,
                'event_type' => 'device_created',
                'severity' => 'info',
                'details' => [
                    'name' => $device->name,
                    'identifier' => $device->device_identifier,
                    'seeded' => true,
                ],
            ]);
        }
    }
}
