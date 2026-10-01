<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$d = App\Models\Device::firstOrCreate(
    ['device_identifier' => 'DESK-TEST-01'],
    [
        'name' => 'Client Test PC',
        'location' => 'Client Office / Demo PC',
        'device_type' => 'desktop',
        'status' => 'online',
        'stream_status' => 'idle',
        'screen_resolution' => '1920x1080',
        'fps' => 30,
        'ip_address' => '127.0.0.1',
    ]
);

App\Models\DevicePairingTicket::where('device_id', $d->id)->where('status', 'active')->update(['status' => 'revoked']);
App\Models\DevicePairingTicket::create([
    'device_id' => $d->id,
    'pairing_code' => 'PC-3333',
    'pairing_token' => Illuminate\Support\Str::random(40),
    'status' => 'active',
    'expires_at' => now()->addDays(30),
    'attempt_count' => 0
]);

App\Models\DesktopTabletMapping::firstOrCreate(['desktop_id' => $d->id, 'tablet_id' => 7]);
App\Models\DesktopTabletMapping::firstOrCreate(['desktop_id' => $d->id, 'tablet_id' => 9]);

echo "SUCCESS: Registered " . $d->name . " (" . $d->device_identifier . ") with 30-day Pairing Code: PC-3333\n";
