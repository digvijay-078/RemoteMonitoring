<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabletClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_tablet_pwa_shell_loads_successfully(): void
    {
        $response = $this->get('/tablet');
        $response->assertStatus(200);
        $response->assertSee('RemoteMonitor Client');
        $response->assertSee('manifest.json');
        $response->assertSee('idb.js');
        $response->assertSee('app.js');
    }

    public function test_tablet_client_cannot_access_admin_endpoints(): void
    {
        $response = $this->get('/admin/devices');
        $response->assertRedirect('/admin/login');
    }

    public function test_device_can_access_authenticated_device_api_with_bearer_token(): void
    {
        $device = Device::create([
            'device_identifier' => 'TAB-001',
            'name' => 'Living Room Tablet',
            'location' => 'Floor 1',
            'status' => 'online',
        ]);

        $plainToken = 'rmt_live_' . bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        DeviceCredential::create([
            'device_id' => $device->id,
            'token_prefix' => substr($plainToken, 0, 16),
            'token_hash' => $tokenHash,
        ]);

        $response = $this->getJson('/api/v1/device/me', [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'device' => [
                'identifier' => 'TAB-001',
                'status' => 'online',
            ],
        ]);
    }

    public function test_device_api_rejects_unauthenticated_requests(): void
    {
        $response = $this->getJson('/api/v1/device/me');
        $response->assertStatus(401);
    }

    public function test_disabled_device_token_is_rejected_with_forbidden(): void
    {
        $device = Device::create([
            'device_identifier' => 'TAB-002',
            'name' => 'Disabled Tablet',
            'location' => 'Basement',
            'status' => 'disabled',
        ]);

        $plainToken = 'rmt_live_' . bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        DeviceCredential::create([
            'device_id' => $device->id,
            'token_prefix' => substr($plainToken, 0, 16),
            'token_hash' => $tokenHash,
        ]);

        $response = $this->getJson('/api/v1/device/me', [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        $response->assertStatus(403);
    }
}
