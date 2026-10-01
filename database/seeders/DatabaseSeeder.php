<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Device;
use App\Models\DeviceCredential;
use App\Models\DevicePairingTicket;
use App\Models\DesktopTabletMapping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. System Admin
        $admin = User::firstOrNew(['email' => 'admin@remotemonitor.internal']);
        $admin->name = 'System Administrator';
        $admin->password = 'password';
        $admin->role = 'admin';
        $admin->save();

        // 2. Windows Desktop (Screen + WASAPI Audio Source)
        $desktopToken = 'rmt_live_desktop_hq01_master_key_2026_unattended_stream';
        $desktop = Device::firstOrNew(['device_identifier' => 'DESK-HQ-01']);
        $desktop->uuid = '0362ed27-d43d-4ca7-8db4-e221a3676ba2';
        $desktop->name = 'HQ Primary Windows PC';
        $desktop->device_type = 'desktop';
        $desktop->location = 'HQ Server Room / Primary PC';
        $desktop->status = 'online';
        $desktop->stream_status = 'idle';
        $desktop->active_viewers_count = 0;
        $desktop->last_seen_at = now();
        $desktop->save();

        DeviceCredential::where('device_id', $desktop->id)->delete();
        DeviceCredential::create([
            'device_id' => $desktop->id,
            'token_prefix' => substr($desktopToken, 0, 12),
            'token_hash' => hash('sha256', $desktopToken),
        ]);

        // 3. Android Tablet (Client Viewer)
        $tabletToken = 'rmt_live_tablet_pad01_master_key_2026_viewer_stream';
        $tablet = Device::firstOrNew(['device_identifier' => 'TAB-PAD-01']);
        $tablet->uuid = '00771be9-98e1-479d-90f8-937f1e9160ef';
        $tablet->name = 'Android Tablet (Live Viewer)';
        $tablet->device_type = 'tablet';
        $tablet->location = 'Operations Floor / Mobile';
        $tablet->status = 'online';
        $tablet->last_seen_at = now();
        $tablet->save();

        DeviceCredential::where('device_id', $tablet->id)->delete();
        DeviceCredential::create([
            'device_id' => $tablet->id,
            'token_prefix' => substr($tabletToken, 0, 12),
            'token_hash' => hash('sha256', $tabletToken),
        ]);

        // Active 7-day Pairing Ticket
        DevicePairingTicket::where('device_id', $tablet->id)->delete();
        DevicePairingTicket::create([
            'device_id' => $tablet->id,
            'pairing_code' => 'RM-8888',
            'pairing_token' => Str::random(32),
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ]);

        // 4. Desktop ↔ Tablet Authorized Mapping
        DesktopTabletMapping::firstOrCreate([
            'desktop_id' => $desktop->id,
            'tablet_id' => $tablet->id,
        ], [
            'is_active' => true,
            'authorized_at' => now(),
        ]);

        // 5. Update demo fleet config file
        $fleetData = [
            'server_url' => 'http://127.0.0.1:8000',
            'ws_url' => 'ws://127.0.0.1:8080',
            'app_key' => config('reverb.apps.apps.0.key', 'nw2zhrpowiazy7xm9esc'),
            'desktop1' => [
                'id' => $desktop->id,
                'identifier' => $desktop->device_identifier,
                'uuid' => $desktop->uuid,
                'name' => $desktop->name,
                'token' => $desktopToken,
            ],
            'tablet1' => [
                'id' => $tablet->id,
                'identifier' => $tablet->device_identifier,
                'uuid' => $tablet->uuid,
                'name' => $tablet->name,
                'token' => $tabletToken,
                'pairing_code' => 'RM-8888',
            ],
        ];
        @file_put_contents(storage_path('app/demo_fleet.json'), json_encode($fleetData, JSON_PRETTY_PRINT));
    }
}
