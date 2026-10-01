<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

foreach (App\Models\WebRtcSession::latest()->take(6)->get() as $s) {
    echo "Session: " . $s->session_id . " | Desktop: " . $s->desktop_id . " | Status: " . $s->status . " | Created: " . $s->created_at . PHP_EOL;
}
