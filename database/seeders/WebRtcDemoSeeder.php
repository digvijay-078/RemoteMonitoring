<?php

namespace Database\Seeders;

use App\Models\DesktopTabletMapping;
use App\Models\Device;
use App\Models\DevicePairingTicket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WebRtcDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@remotemonitor.local'],
            [
                'name' => 'Fleet Administrator',
                'password' => Hash::make('admin123456'),
                'role' => 'admin',
            ]
        );

        // 2. Desktop Device
        $desktop = Device::firstOrCreate(
            ['device_identifier' => 'DESK-HQ-1'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Workstation Alpha (Win11)',
                'location' => 'HQ Lab Desk 4',
                'device_type' => 'desktop',
                'status' => 'offline',
                'stream_status' => 'idle',
                'screen_resolution' => '1920x1080',
                'fps' => 30,
            ]
        );

        // 3. Tablet Device
        $tablet = Device::firstOrCreate(
            ['device_identifier' => 'TAB-HQ-1'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Supervisor Tablet A',
                'location' => 'Zone 1 Mobile',
                'device_type' => 'tablet',
                'status' => 'offline',
                'stream_status' => 'idle',
            ]
        );

        // 4. Desktop ↔ Tablet Authorization Mapping
        DesktopTabletMapping::firstOrCreate([
            'tablet_id' => $tablet->id,
            'desktop_id' => $desktop->id,
        ], [
            'created_by' => $admin->id,
        ]);

        // 5. Generate active pairing tickets
        DevicePairingTicket::where('device_id', $desktop->id)->delete();
        DevicePairingTicket::create([
            'device_id' => $desktop->id,
            'pairing_code' => 'PC8899',
            'pairing_token' => Str::random(40),
            'expires_at' => now()->addHours(2),
            'status' => 'active',
            'created_by_user_id' => $admin->id,
        ]);

        DevicePairingTicket::where('device_id', $tablet->id)->delete();
        DevicePairingTicket::create([
            'device_id' => $tablet->id,
            'pairing_code' => 'TB8899',
            'pairing_token' => Str::random(40),
            'expires_at' => now()->addHours(2),
            'status' => 'active',
            'created_by_user_id' => $admin->id,
        ]);

        echo "Seeded: Desktop DESK-HQ-1 (Code: PC8899), Tablet TAB-HQ-1 (Code: TB8899), Mapping Authorized.\n";
    }
}

