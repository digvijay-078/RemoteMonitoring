<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DevicePairingTicket;
use App\Models\DeviceCredential;
use App\Models\User;
use App\Services\PairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevicePairingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Device $device;
    protected PairingService $pairingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@pairing.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->device = Device::create([
            'device_identifier' => 'TAB-001',
            'name' => 'Test Tablet',
            'location' => 'Room 101',
            'status' => 'pending_pair',
        ]);

        $this->pairingService = app(PairingService::class);
    }

    public function test_admin_can_generate_pairing_ticket(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/devices/' . $this->device->id . '/pair-ticket');

        $this->assertDatabaseHas('device_pairing_tickets', [
            'device_id' => $this->device->id,
            'status' => 'active',
        ]);

        $ticket = DevicePairingTicket::where('device_id', $this->device->id)->first();
        $this->assertNotNull($ticket);
        $this->assertStringStartsWith('RM-', $ticket->pairing_code);
        $this->assertTrue($ticket->isActive());
    }

    public function test_tablet_can_pair_successfully_with_valid_code(): void
    {
        $ticket = $this->pairingService->generatePairingTicket($this->device, $this->admin->id);

        $response = $this->postJson('/api/v1/device/pair', [
            'pairing_code' => $ticket->pairing_code,
            'hardware_info' => [
                'screenWidth' => 1920,
                'screenHeight' => 1200,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $json = $response->json();
        $this->assertArrayHasKey('device_token', $json['data']);
        $this->assertArrayHasKey('device', $json['data']);

        $plainToken = $json['data']['device_token'];
        $this->assertStringStartsWith('rmt_live_', $plainToken);

        // Assert ticket status changed to used
        $this->assertEquals('used', $ticket->fresh()->status);

        // Assert device status is online and paired_at is set
        $this->device->refresh();
        $this->assertEquals('online', $this->device->status);
        $this->assertNotNull($this->device->paired_at);

        // Assert plaintext token is NOT stored in the database! Only SHA-256 hash
        $expectedHash = hash('sha256', $plainToken);
        $this->assertDatabaseHas('device_credentials', [
            'device_id' => $this->device->id,
            'token_hash' => $expectedHash,
        ]);
        $this->assertDatabaseMissing('device_credentials', [
            'token_hash' => $plainToken,
        ]);
    }

    public function test_pairing_fails_with_invalid_code(): void
    {
        $response = $this->postJson('/api/v1/device/pair', [
            'pairing_code' => 'INVALID-CODE',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_pairing_fails_with_expired_code(): void
    {
        $ticket = DevicePairingTicket::create([
            'device_id' => $this->device->id,
            'pairing_code' => 'RM-EXPD',
            'pairing_token' => 'token-exp',
            'status' => 'active',
            'expires_at' => now()->subMinute(),
            'created_by_user_id' => $this->admin->id,
        ]);

        $response = $this->postJson('/api/v1/device/pair', [
            'pairing_code' => 'RM-EXPD',
        ]);

        $response->assertStatus(422);
        $this->assertEquals('expired', $ticket->fresh()->status);
    }

    public function test_pairing_fails_with_already_used_code(): void
    {
        $ticket = DevicePairingTicket::create([
            'device_id' => $this->device->id,
            'pairing_code' => 'RM-USED',
            'pairing_token' => 'token-used',
            'status' => 'used',
            'expires_at' => now()->addMinutes(10),
            'created_by_user_id' => $this->admin->id,
        ]);

        $response = $this->postJson('/api/v1/device/pair', [
            'pairing_code' => 'RM-USED',
        ]);

        $response->assertStatus(422);
    }

    public function test_pairing_fails_with_revoked_code(): void
    {
        $ticket = DevicePairingTicket::create([
            'device_id' => $this->device->id,
            'pairing_code' => 'RM-REVK',
            'pairing_token' => 'token-revk',
            'status' => 'revoked',
            'expires_at' => now()->addMinutes(10),
            'created_by_user_id' => $this->admin->id,
        ]);

        $response = $this->postJson('/api/v1/device/pair', [
            'pairing_code' => 'RM-REVK',
        ]);

        $response->assertStatus(422);
    }
}
