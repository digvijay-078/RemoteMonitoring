<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'admin@remotemonitor.internal')->first();
if (!$user) {
    $user = new User();
    $user->email = 'admin@remotemonitor.internal';
    $user->name = 'System Administrator';
}

$user->password = Hash::make('password');
$user->role = 'admin';
$user->save();

echo "SUCCESS: Admin user 'admin@remotemonitor.internal' password set to 'password' (Role: {$user->role}, ID: {$user->id})\n";
