<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;
use App\Models\DevicePairingTicket;
use App\Models\DesktopTabletMapping;
use Illuminate\Support\Str;

// 1. Ensure Tablet 1 (TAB-001)
$tablet1 = Device::firstOrCreate(
    ['device_identifier' => 'TAB-001'],
    [
        'uuid' => (string) Str::uuid(),
        'name' => 'Client Review Tablet 1',
        'device_type' => 'tablet',
        'status' => 'online',
        'last_seen_at' => now(),
    ]
);

DevicePairingTicket::updateOrCreate(
    ['pairing_code' => 'TAB-7777'],
    [
        'device_id' => $tablet1->id,
        'pairing_token' => Str::random(32),
        'status' => 'active',
        'expires_at' => now()->addYear(),
        'attempt_count' => 0,
    ]
);
echo "Ticket TAB-7777 ensured for Tablet #{$tablet1->id} (TAB-001)\n";

// 2. Ensure Tablet 2 (TAB-002)
$tablet2 = Device::withTrashed()->where('device_identifier', 'TAB-002')->first();
if ($tablet2) {
    if ($tablet2->trashed()) $tablet2->restore();
} else {
    $tablet2 = Device::create([
        'device_identifier' => 'TAB-002',
        'uuid' => (string) Str::uuid(),
        'name' => 'Client Review Tablet 2',
        'device_type' => 'tablet',
        'location' => 'Client Office / Mobile',
        'status' => 'online',
        'last_seen_at' => now(),
    ]);
}

DevicePairingTicket::updateOrCreate(
    ['pairing_code' => 'TAB-8888'],
    [
        'device_id' => $tablet2->id,
        'pairing_token' => Str::random(32),
        'status' => 'active',
        'expires_at' => now()->addYear(),
        'attempt_count' => 0,
    ]
);
echo "Ticket TAB-8888 ensured for Tablet #{$tablet2->id} (TAB-002)\n";

// 3. Map all desktops to Tablet 1 and Tablet 2 so client immediately sees live screens
$desktops = Device::where('device_type', 'desktop')->orWhere('id', '!=', $tablet1->id)->where('id', '!=', $tablet2->id)->get();
foreach ($desktops as $desktop) {
    if ($desktop->id === $tablet1->id || $desktop->id === $tablet2->id) continue;
    
    DesktopTabletMapping::firstOrCreate([
        'desktop_id' => $desktop->id,
        'tablet_id' => $tablet1->id,
    ], ['is_active' => true, 'authorized_at' => now()]);

    DesktopTabletMapping::firstOrCreate([
        'desktop_id' => $desktop->id,
        'tablet_id' => $tablet2->id,
    ], ['is_active' => true, 'authorized_at' => now()]);

    echo "Mapped Desktop #{$desktop->id} ({$desktop->name}) to Tablets\n";
}
