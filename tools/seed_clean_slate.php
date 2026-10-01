<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Device;
use App\Models\DesktopTabletMapping;
use App\Models\DevicePairingTicket;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "========================================================\n";
echo "  REMOTEMONITOR — FRESH CLEAN SLATE INITIALIZATION\n";
echo "========================================================\n\n";

// 1. Create Admin User
$admin = User::create([
    'name' => 'System Administrator',
    'email' => 'admin@remotemonitor.internal',
    'password' => Hash::make('password'),
    'role' => 'admin',
]);
echo "[1/4] Admin Account Created:\n";
echo "      Email:    admin@remotemonitor.internal\n";
echo "      Password: password\n\n";

// 2. Create Windows Desktop (Screen + Audio Source)
$desktopPlainToken = 'rmt_live_' . bin2hex(random_bytes(24));
$desktop = Device::create([
    'device_identifier' => 'DESK-HQ-01',
    'name' => 'HQ Primary Windows PC',
    'device_type' => 'desktop',
    'location' => 'HQ Server Room / Primary PC',
    'status' => 'online',
    'stream_status' => 'idle',
    'active_viewers_count' => 0,
    'last_seen_at' => now(),
]);

\App\Models\DeviceCredential::create([
    'device_id' => $desktop->id,
    'token_prefix' => substr($desktopPlainToken, 0, 12),
    'token_hash' => hash('sha256', $desktopPlainToken),
]);

echo "[2/4] Windows Desktop Registered:\n";
echo "      Identifier: {$desktop->device_identifier}\n";
echo "      Name:       {$desktop->name}\n";
echo "      UUID:       {$desktop->uuid}\n";
echo "      Token:      {$desktopPlainToken}\n\n";

// 3. Create Android Tablet (Viewer Client)
$tabletPlainToken = 'rmt_live_' . bin2hex(random_bytes(24));
$tablet = Device::create([
    'device_identifier' => 'TAB-PAD-01',
    'name' => 'Android Tablet (Live Viewer)',
    'device_type' => 'tablet',
    'location' => 'Operations Floor / Mobile',
    'status' => 'offline',
    'last_seen_at' => null,
]);

\App\Models\DeviceCredential::create([
    'device_id' => $tablet->id,
    'token_prefix' => substr($tabletPlainToken, 0, 12),
    'token_hash' => hash('sha256', $tabletPlainToken),
]);

// Issue a friendly 6-char pairing ticket for the tablet: RM-8888
$ticket = DevicePairingTicket::create([
    'device_id' => $tablet->id,
    'pairing_code' => 'RM-8888',
    'pairing_token' => Str::random(32),
    'status' => 'active',
    'expires_at' => now()->addDays(7),
]);
echo "[3/4] Android Tablet Registered:\n";
echo "      Identifier:   {$tablet->device_identifier}\n";
echo "      Pairing Code: RM-8888 (valid for 7 days)\n";
echo "      Token:        {$tabletPlainToken}\n\n";

// 4. Create Desktop ↔ Tablet Authorized Mapping
$mapping = DesktopTabletMapping::create([
    'desktop_id' => $desktop->id,
    'tablet_id' => $tablet->id,
    'is_active' => true,
    'authorized_at' => now(),
]);
echo "[4/4] Mapping Authorized:\n";
echo "      {$desktop->device_identifier} ──(LIVE WebRTC)──> {$tablet->device_identifier}\n\n";

// Write clean config for test tools and agents
$fleetData = [
    'server_url' => 'http://127.0.0.1:8000',
    'ws_url' => 'ws://127.0.0.1:8080',
    'app_key' => config('reverb.apps.apps.0.key', 'nw2zhrpowiazy7xm9esc'),
    'desktop1' => [
        'id' => $desktop->id,
        'identifier' => $desktop->device_identifier,
        'uuid' => $desktop->uuid,
        'name' => $desktop->name,
        'token' => $desktopPlainToken,
    ],
    'tablet1' => [
        'id' => $tablet->id,
        'identifier' => $tablet->device_identifier,
        'uuid' => $tablet->uuid,
        'name' => $tablet->name,
        'token' => $tabletPlainToken,
        'pairing_code' => 'RM-8888',
    ],
];
file_put_contents(__DIR__ . '/../storage/app/demo_fleet.json', json_encode($fleetData, JSON_PRETTY_PRINT));

echo "========================================================\n";
echo "  CLEAN SLATE SEEDING COMPLETED SUCCESSFULLY!\n";
echo "========================================================\n";
