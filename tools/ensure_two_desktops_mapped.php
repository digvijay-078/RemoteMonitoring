<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;
use Illuminate\Support\Facades\DB;

$tablet = Device::where('device_type', 'tablet')->orderBy('id', 'desc')->first();
$d1 = Device::where('device_identifier', 'DESK-HQ-01')->first();
$d2 = Device::where('device_identifier', 'DESK-LAB-02')->first();

echo "Active Tablet: {$tablet->device_identifier} (ID: {$tablet->id}, UUID: {$tablet->uuid})\n";
echo "Desktop 1: {$d1->device_identifier} (ID: {$d1->id})\n";
echo "Desktop 2: {$d2->device_identifier} (ID: {$d2->id})\n";

foreach ([$d1, $d2] as $desk) {
    if (!$desk) continue;
    $exists = DB::table('desktop_tablet_mappings')
        ->where('desktop_id', $desk->id)
        ->where('tablet_id', $tablet->id)
        ->first();

    if (!$exists) {
        DB::table('desktop_tablet_mappings')->insert([
            'desktop_id' => $desk->id,
            'tablet_id' => $tablet->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Mapped {$desk->device_identifier} -> {$tablet->device_identifier}\n";
    } else {
        echo "Mapping already exists: {$desk->device_identifier} -> {$tablet->device_identifier}\n";
    }
}

echo "Current assigned desktops count: " . $tablet->assignedDesktops()->count() . "\n";
foreach ($tablet->assignedDesktops()->get() as $ad) {
    echo " - {$ad->device_identifier} : {$ad->name} ({$ad->uuid})\n";
}
