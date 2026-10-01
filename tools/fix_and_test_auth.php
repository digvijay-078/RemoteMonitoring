<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Set plain password - Laravel 11 'password' => 'hashed' cast will hash it automatically once!
$u1 = User::where('email', 'admin@remotemonitor.internal')->first();
$u1->password = 'password';
$u1->role = 'admin';
$u1->save();

$u2 = User::where('email', 'admin@remotemonitor.local')->first();
$u2->password = 'admin123456';
$u2->role = 'admin';
$u2->save();

echo "Testing Auth::attempt for admin@remotemonitor.internal with 'password':\n";
$res1 = Auth::attempt(['email' => 'admin@remotemonitor.internal', 'password' => 'password']);
echo "Result 1: " . ($res1 ? 'SUCCESS (TRUE)' : 'FAILED (FALSE)') . "\n\n";

echo "Testing Auth::attempt for admin@remotemonitor.local with 'admin123456':\n";
$res2 = Auth::attempt(['email' => 'admin@remotemonitor.local', 'password' => 'admin123456']);
echo "Result 2: " . ($res2 ? 'SUCCESS (TRUE)' : 'FAILED (FALSE)') . "\n";
