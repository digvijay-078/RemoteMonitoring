<?php

namespace App\Console\Commands;

use App\Models\DesktopTabletMapping;
use App\Models\Device;
use App\Models\DeviceCredential;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionStreamDemo extends Command
{
    protected $signature = 'stream:provision {--reset : Reset all existing devices and sessions}';
    protected $description = 'Provision demo Desktop PCs and Android Tablet viewers with valid mappings and credentials';

    public function handle(): int
    {
        if ($this->option('reset')) {
            $this->warn('Resetting all existing devices, credentials, and mappings...');
            DesktopTabletMapping::truncate();
            DeviceCredential::truncate();
            Device::truncate();
        }

        // 1. Ensure Admin User Exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@remotemonitor.local'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $this->info("Admin User: admin@remotemonitor.local / password123");

        // 2. Create Desktop 1 (Primary Windows PC)
        [$desktop1, $desktopToken1] = $this->createOrUpdateDevice(
            identifier: 'DESK-HQ-01',
            name: 'HQ Primary Windows PC',
            type: 'desktop',
            location: 'Building A - Room 101'
        );

        // 3. Create Desktop 2 (Secondary Workstation)
        [$desktop2, $desktopToken2] = $this->createOrUpdateDevice(
            identifier: 'DESK-HQ-02',
            name: 'Engineering CAD Workstation',
            type: 'desktop',
            location: 'Lab 2'
        );

        // 4. Create Tablet 1 (Primary Viewer)
        [$tablet1, $tabletToken1] = $this->createOrUpdateDevice(
            identifier: 'TAB-PAD-01',
            name: 'Samsung Galaxy Tab S9 (Primary)',
            type: 'tablet',
            location: 'Control Center'
        );

        // 5. Create Tablet 2 (Secondary Viewer)
        [$tablet2, $tabletToken2] = $this->createOrUpdateDevice(
            identifier: 'TAB-PAD-02',
            name: 'Lenovo Tab P12 (Secondary)',
            type: 'tablet',
            location: 'Supervisor Desk'
        );

        // 6. Map Tablet 1 -> Desktop 1 and Desktop 2 (Multi-Desktop test)
        DesktopTabletMapping::firstOrCreate([
            'tablet_id' => $tablet1->id,
            'desktop_id' => $desktop1->id,
        ], ['created_by' => $admin->id]);

        DesktopTabletMapping::firstOrCreate([
            'tablet_id' => $tablet1->id,
            'desktop_id' => $desktop2->id,
        ], ['created_by' => $admin->id]);

        // 7. Map Tablet 2 -> Desktop 1 (Multi-Viewer test for same desktop)
        DesktopTabletMapping::firstOrCreate([
            'tablet_id' => $tablet2->id,
            'desktop_id' => $desktop1->id,
        ], ['created_by' => $admin->id]);

        $this->newLine();
        $this->info('===========================================================');
        $this->info('  REMOTEMONITOR STREAMING FLEET PROVISIONED SUCCESSFULLY');
        $this->info('===========================================================');
        $this->table(
            ['Role', 'Identifier', 'UUID', 'Token Prefix', 'Assigned Mappings'],
            [
                ['Desktop 1', $desktop1->device_identifier, $desktop1->uuid, substr($desktopToken1, 0, 16) . '...', 'Viewed by TAB-PAD-01, TAB-PAD-02'],
                ['Desktop 2', $desktop2->device_identifier, $desktop2->uuid, substr($desktopToken2, 0, 16) . '...', 'Viewed by TAB-PAD-01'],
                ['Tablet 1', $tablet1->device_identifier, $tablet1->uuid, substr($tabletToken1, 0, 16) . '...', 'Assigned: DESK-HQ-01, DESK-HQ-02'],
                ['Tablet 2', $tablet2->device_identifier, $tablet2->uuid, substr($tabletToken2, 0, 16) . '...', 'Assigned: DESK-HQ-01'],
            ]
        );

        // Write credentials to a JSON output file for the agent and E2E test scripts
        $configData = [
            'server_url' => config('app.url', 'http://127.0.0.1:8000'),
            'ws_url' => 'ws://' . env('REVERB_HOST', '127.0.0.1') . ':' . env('REVERB_PORT', 8080),
            'app_key' => env('REVERB_APP_KEY', 'nw2zhrpowiazy7xm9esc'),
            'desktop1' => [
                'id' => $desktop1->id,
                'identifier' => $desktop1->device_identifier,
                'uuid' => $desktop1->uuid,
                'name' => $desktop1->name,
                'token' => $desktopToken1,
            ],
            'desktop2' => [
                'id' => $desktop2->id,
                'identifier' => $desktop2->device_identifier,
                'uuid' => $desktop2->uuid,
                'name' => $desktop2->name,
                'token' => $desktopToken2,
            ],
            'tablet1' => [
                'id' => $tablet1->id,
                'identifier' => $tablet1->device_identifier,
                'uuid' => $tablet1->uuid,
                'name' => $tablet1->name,
                'token' => $tabletToken1,
            ],
            'tablet2' => [
                'id' => $tablet2->id,
                'identifier' => $tablet2->device_identifier,
                'uuid' => $tablet2->uuid,
                'name' => $tablet2->name,
                'token' => $tabletToken2,
            ],
        ];

        file_put_contents(base_path('storage/app/demo_fleet.json'), json_encode($configData, JSON_PRETTY_PRINT));
        $this->info('Configuration saved to storage/app/demo_fleet.json');

        return self::SUCCESS;
    }

    protected function createOrUpdateDevice(string $identifier, string $name, string $type, string $location): array
    {
        $device = Device::firstOrNew(['device_identifier' => $identifier]);
        $device->name = $name;
        $device->device_type = $type;
        $device->location = $location;
        $device->status = 'online';
        $device->stream_status = 'idle';
        $device->paired_at = now();
        $device->last_seen_at = now();
        if (empty($device->uuid)) {
            $device->uuid = (string) Str::uuid();
        }
        $device->save();

        // Create or refresh credential
        $plainToken = 'rmt_live_' . bin2hex(random_bytes(32));
        $tokenPrefix = substr($plainToken, 0, 16);
        $tokenHash = hash('sha256', $plainToken);

        $device->credentials()->update(['revoked_at' => now()]);

        DeviceCredential::create([
            'device_id' => $device->id,
            'token_prefix' => $tokenPrefix,
            'token_hash' => $tokenHash,
            'last_rotated_at' => now(),
        ]);

        return [$device, $plainToken];
    }
}
