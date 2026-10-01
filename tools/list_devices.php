<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Device::all() as $d) {
    echo "ID: {$d->id} | Identifier: {$d->device_identifier} | UUID: {$d->uuid} | Name: {$d->name} | Type: {$d->device_type} | Status: {$d->status} | Stream: {$d->stream_status}\n";
}
