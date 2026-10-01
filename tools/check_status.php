<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DEVICES ===" . PHP_EOL;
foreach (\App\Models\Device::all() as $d) {
    echo "ID {$d->id}: {$d->name} ({$d->device_identifier}) [UUID: {$d->uuid}] - status: {$d->status}" . PHP_EOL;
}

echo PHP_EOL . "=== ALL TICKETS ===" . PHP_EOL;
foreach (\App\Models\DevicePairingTicket::all() as $t) {
    echo "Ticket #{$t->id}: Code '{$t->pairing_code}' -> Device #{$t->device_id} [Status: {$t->status}] [Expires: {$t->expires_at}]" . PHP_EOL;
}

echo PHP_EOL . "=== DETAILS OF DESK-4D8A ===" . PHP_EOL;
$d = \App\Models\Device::where('device_identifier', 'DESK-4D8A')->first();
if ($d) {
    if ($d->last_seen_at && $d->last_seen_at->lt(now()->subHours(2))) {
        $d->update(['status' => 'offline', 'stream_status' => 'offline']);
    }
    echo json_encode($d->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
    echo PHP_EOL . "=== AUDIT LOGS FOR DESK-4D8A ===" . PHP_EOL;
    foreach (\App\Models\DeviceAuditLog::where('device_id', $d->id)->latest()->take(10)->get() as $l) {
        echo "{$l->created_at} [{$l->event_type}] " . json_encode($l->details) . PHP_EOL;
    }
}
