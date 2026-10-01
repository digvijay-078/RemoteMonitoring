<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceRealtimeAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createDeviceWithToken(string $identifier = 'TAB-001', string $status = 'online'): array
    {
        $device = Device::create([
            'uuid' => (string) Str::uuid(),
            'device_identifier' => $identifier,
            'name' => "Display {$identifier}",
            'location' => 'ICU Room 1',
            'status' => $status,
            'view_mode' => 'single',
            'cycle_interval_seconds' => 15,
        ]);

        $plainToken = 'rmt_live_' . bin2hex(random_bytes(32)); // 256-bit token
        $tokenHash = hash('sha256', $plainToken);

        $credential = DeviceCredential::create([
            'device_id' => $device->id,
            'token_hash' => $tokenHash,
            'token_prefix' => substr($plainToken, 0, 16),
            'last_used_at' => now(),
        ]);

        return [$device, $plainToken, $credential];
    }

    public function test_valid_device_can_authorize_its_own_private_channel(): void
    {
        [$device, $token] = $this->createDeviceWithToken('TAB-001');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-device.{$device->uuid}",
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth']);

        $authString = $response->json('auth');
        $this->assertStringContainsString(':', $authString);
    }

    public function test_invalid_credential_is_rejected(): void
    {
        [$device] = $this->createDeviceWithToken('TAB-001');

        $response = $this->withHeader('Authorization', 'Bearer rmt_live_invalidtoken12345678901234567890')
            ->postJson('/api/v1/device/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-device.{$device->uuid}",
            ]);

        $response->assertStatus(401);
    }

    public function test_revoked_credential_is_rejected(): void
    {
        [$device, $token, $credential] = $this->createDeviceWithToken('TAB-001');

        // Revoke credential
        $credential->update(['revoked_at' => now()]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-device.{$device->uuid}",
            ]);

        $response->assertStatus(401);
    }

    public function test_disabled_device_is_rejected(): void
    {
        [$device, $token] = $this->createDeviceWithToken('TAB-001', 'disabled');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-device.{$device->uuid}",
            ]);

        $response->assertStatus(403);
    }

    public function test_cross_device_channel_access_is_forbidden(): void
    {
        // Device A (TAB-001) and Device B (TAB-002)
        [$deviceA, $tokenA] = $this->createDeviceWithToken('TAB-001');
        [$deviceB, $tokenB] = $this->createDeviceWithToken('TAB-002');

        // Device A attempts to authorize Device B's channel
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/device/broadcasting/auth', [
                'socket_id' => '99999.88888',
                'channel_name' => "private-device.{$deviceB->uuid}",
            ]);

        // Must be explicitly rejected with 403 Forbidden
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'error' => "Cross-device access forbidden. Device TAB-001 cannot subscribe to private-device.{$deviceB->uuid}.",
        ]);
    }

    public function test_device_bearer_token_cannot_access_admin_endpoints(): void
    {
        [$device, $token] = $this->createDeviceWithToken('TAB-001');

        // Device token cannot access admin overview
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/admin/overview');

        // Unauthenticated for web guard -> redirected to admin login
        $response->assertRedirect('/admin/login');
    }

    public function test_device_can_reconnect_without_pairing_again(): void
    {
        [$device, $token] = $this->createDeviceWithToken('TAB-001');

        // First connection / authorization
        $res1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/broadcasting/auth', [
                'socket_id' => '11111.11111',
                'channel_name' => "private-device.{$device->uuid}",
            ]);
        $res1->assertStatus(200);

        // Simulated network reconnect (new socket_id, same token)
        $res2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/broadcasting/auth', [
                'socket_id' => '22222.22222',
                'channel_name' => "private-device.{$device->uuid}",
            ]);
        $res2->assertStatus(200);
        $this->assertNotEmpty($res2->json('auth'));
    }

    public function test_admin_disabling_device_prevents_future_authentication_and_heartbeat(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@remotemonitor.local',
            'role' => 'admin',
        ]);

        [$device, $token] = $this->createDeviceWithToken('TAB-001');

        // Admin disables the device
        $this->actingAs($admin)
            ->post("/admin/devices/{$device->id}/toggle");

        $device->refresh();
        $this->assertEquals('disabled', $device->status);

        // Subsequent channel authorization must fail
        $authRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/broadcasting/auth', [
                'socket_id' => '33333.33333',
                'channel_name' => "private-device.{$device->uuid}",
            ]);
        $authRes->assertStatus(401); // Credential was revoked on toggle disable

        // Subsequent heartbeat must fail
        $hbRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/heartbeat', [
                'action' => 'HEARTBEAT',
                'device_id' => 'TAB-001',
                'timestamp' => now()->timestamp,
            ]);
        $hbRes->assertStatus(401);
    }
}

