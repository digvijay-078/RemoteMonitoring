<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Admin 1
$u1 = User::firstOrNew(['email' => 'admin@remotemonitor.internal']);
$u1->name = 'System Administrator';
$u1->password = Hash::make('password');
$u1->role = 'admin';
$u1->save();
echo "User 1 configured: {$u1->email} (Password: password)\n";

// Admin 2
$u2 = User::firstOrNew(['email' => 'admin@remotemonitor.local']);
$u2->name = 'Phase 1 Admin';
$u2->password = Hash::make('admin123456');
$u2->role = 'admin';
$u2->save();
echo "User 2 configured: {$u2->email} (Password: admin123456)\n";
