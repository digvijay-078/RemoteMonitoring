<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$desktop = App\Models\Device::find(8);
// Generate a temporary plain token for desktop 8
$plainToken = 'rmt_test_offer_' . bin2hex(random_bytes(16));
$tokenHash = hash('sha256', $plainToken);

App\Models\DeviceCredential::create([
    'device_id' => $desktop->id,
    'token_hash' => $tokenHash,
    'token_prefix' => substr($plainToken, 0, 12),
    'created_at' => now(),
    'updated_at' => now(),
]);

echo "Created test credential for Desktop 8:\n";
echo "Token: {$plainToken}\n";

$ch = curl_init('http://127.0.0.1:8000/api/v1/webrtc/signal/offer');
$payload = json_encode([
    'session_id' => 'f3725f4f-1120-4a07-bee4-2b5b1fbe389f',
    'sdp' => [
        'type' => 'offer',
        'sdp' => "v=0\r\no=- 123 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n"
    ]
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    "Authorization: Bearer {$plainToken}"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: {$response}\n";
