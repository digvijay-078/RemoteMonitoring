<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

foreach (App\Models\DeviceAuditLog::latest()->take(15)->get() as $log) {
    echo "[" . $log->created_at . "] " . $log->event_type . " | Device: " . $log->device_id . " | Details: " . json_encode($log->details) . PHP_EOL;
}
