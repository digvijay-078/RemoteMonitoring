<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logs = App\Models\DeviceAuditLog::where('device_id', 8)->latest()->take(5)->get();
foreach ($logs as $l) {
    echo "Event: {$l->event_type} | Time: {$l->created_at} | Details: " . json_encode($l->details) . "\n";
}
