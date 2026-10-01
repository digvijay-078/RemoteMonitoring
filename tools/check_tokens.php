<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DeviceCredential;
use App\Models\Device;

echo "DEVICES IN DB:\n";
foreach (Device::all() as $d) {
    echo "ID: {$d->id}, Identifier: {$d->device_identifier}, UUID: {$d->uuid}, Status: {$d->status}\n";
}

echo "\nCREDENTIALS IN DB:\n";
foreach (DeviceCredential::all() as $c) {
    echo "Device ID: {$c->device_id}, Hash: {$c->token_hash}, Prefix: {$c->token_prefix}, Revoked: " . ($c->revoked_at ? $c->revoked_at : 'NULL') . "\n";
}

$tabToken = 'rmt_live_tablet_pad01_master_key_2026_viewer_stream';
$deskToken = 'rmt_live_desktop_hq01_master_key_2026_unattended_stream';

echo "\nCalculated Hashes:\n";
echo "Tablet Hash:  " . hash('sha256', $tabToken) . "\n";
echo "Desktop Hash: " . hash('sha256', $deskToken) . "\n";
