<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$uuid = $argv[1] ?? '0362ed27-d43d-4ca7-8db4-e221a3676ba2';
echo "Broadcasting test WebRtcSessionRequested to {$uuid}...\n";
broadcast(new App\Events\WebRtcSessionRequested(
    $uuid,
    'test-session-' . time(),
    'tab-uuid-test',
    'TAB-008',
    'Tablet',
    []
));
echo "Broadcast complete!\n";
