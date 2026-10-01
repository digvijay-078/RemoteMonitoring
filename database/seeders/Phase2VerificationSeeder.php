<?php

namespace Database\Seeders;

use App\Models\Dashboard;
use App\Models\Device;
use App\Models\DevicePairingTicket;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Phase2VerificationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@remotemonitor.local'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin123456'),
                'role' => 'super_admin',
            ]
        );

        // 2. Default Dashboard
        $dashboard = Dashboard::firstOrCreate(
            ['name' => 'ICU Core Vitals'],
            [
                'uuid' => (string) Str::uuid(),
                'description' => 'Realtime physiological monitoring dashboard',
                'layout_config' => [
                    'columns' => 2,
                    'widgets' => [
                        ['type' => 'vital_ecg', 'title' => 'ECG Lead II'],
                        ['type' => 'vital_spo2', 'title' => 'SpO2 Oxygen Saturation'],
                    ],
                ],
                'refresh_rate_ms' => 1000,
                'version' => 1,
            ]
        );

        // 3. Tablet A: TAB-001
        $deviceA = Device::firstOrCreate(
            ['device_identifier' => 'TAB-001'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'ICU Central Display',
                'location' => 'Building A, Floor 3',
                'status' => 'pending_pair',
                'view_mode' => 'single',
                'cycle_interval_seconds' => 15,
            ]
        );

        Monitor::firstOrCreate(
            ['device_id' => $deviceA->id, 'slot_number' => 1],
            [
                'name' => 'Monitor 01 - Vital Signs',
                'dashboard_id' => $dashboard->id,
                'config_version' => 1,
            ]
        );

        DevicePairingTicket::where('device_id', $deviceA->id)->delete();
        DevicePairingTicket::create([
            'device_id' => $deviceA->id,
            'pairing_code' => 'RM-TAB1',
            'pairing_token' => Str::random(40),
            'status' => 'active',
            'expires_at' => now()->addHours(2),
            'created_by_user_id' => $admin->id,
        ]);

        // 4. Tablet B: TAB-002
        $deviceB = Device::firstOrCreate(
            ['device_identifier' => 'TAB-002'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Emergency Room Display',
                'location' => 'Building B, Ground Floor',
                'status' => 'pending_pair',
                'view_mode' => 'single',
                'cycle_interval_seconds' => 15,
            ]
        );

        Monitor::firstOrCreate(
            ['device_id' => $deviceB->id, 'slot_number' => 1],
            [
                'name' => 'Monitor 01 - Triage Feed',
                'dashboard_id' => $dashboard->id,
                'config_version' => 1,
            ]
        );

        DevicePairingTicket::where('device_id', $deviceB->id)->delete();
        DevicePairingTicket::create([
            'device_id' => $deviceB->id,
            'pairing_code' => 'RM-TAB2',
            'pairing_token' => Str::random(40),
            'status' => 'active',
            'expires_at' => now()->addHours(2),
            'created_by_user_id' => $admin->id,
        ]);
    }
}
