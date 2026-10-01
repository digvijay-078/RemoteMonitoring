<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sessions = App\Models\WebRtcSession::latest()->take(5)->get();
foreach ($sessions as $s) {
    echo "ID: {$s->id} | Desktop: {$s->desktop_id} | Status: {$s->status} | Started: {$s->started_at} | Connected: {$s->connected_at} | Created: {$s->created_at}\n";
}
