<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;
use App\Models\DevicePairingTicket;
use Illuminate\Support\Str;

$desk = Device::where('device_type', 'desktop')->first();
if (!$desk) {
    $desk = Device::create([
        'uuid' => (string) Str::uuid(),
        'device_type' => 'desktop',
        'device_identifier' => 'PC-CLIENT',
        'name' => 'Workstation Client 1',
        'status' => 'online',
        'stream_status' => 'offline',
    ]);
}

foreach (['PC-3333', 'PC-2222', 'RM-8888', 'PC-1111'] as $code) {
    DevicePairingTicket::updateOrCreate(
        ['pairing_code' => $code],
        [
            'device_id' => $desk->id,
            'pairing_token' => Str::random(32),
            'status' => 'active',
            'expires_at' => now()->addYears(2),
            'attempt_count' => 0
        ]
    );
}

echo "Tickets created successfully.\n";
