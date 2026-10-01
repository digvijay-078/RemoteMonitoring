<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceCredential;
use App\Services\DevicePresenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DevicePresenceTest extends TestCase
{
    use RefreshDatabase;

    private function createDevice(string $identifier = 'TAB-001', string $status = 'online'): array
    {
        $device = Device::create([
            'uuid' => (string) Str::uuid(),
            'device_identifier' => $identifier,
            'name' => "Display {$identifier}",
            'location' => 'ICU Room 1',
            'status' => $status,
            'view_mode' => 'single',
            'cycle_interval_seconds' => 15,
            'last_seen_at' => now(),
        ]);

        $plainToken = 'rmt_live_' . bin2hex(random_bytes(32));
        $credential = DeviceCredential::create([
            'device_id' => $device->id,
            'token_hash' => hash('sha256', $plainToken),
            'token_prefix' => substr($plainToken, 0, 16),
            'last_used_at' => now(),
        ]);

        return [$device, $plainToken, $credential];
    }

    public function test_heartbeat_updates_cache_without_database_writes_when_already_online(): void
    {
        [$device, $token] = $this->createDevice('TAB-001', 'online');

        DB::enableQueryLog();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/heartbeat', [
                'action' => 'HEARTBEAT',
                'device_id' => 'TAB-001',
                'timestamp' => now()->timestamp,
                'telemetry' => [
                    'battery' => 95,
                    'isCharging' => true,
                    'network' => 'wifi',
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'action' => 'HEARTBEAT_ACK',
            'status' => 'online',
        ]);

        $queries = DB::getQueryLog();
        $deviceUpdateQueries = array_filter($queries, function ($q) {
            return str_contains(strtolower($q['query']), 'update `devices`');
        });

        // Zero UPDATE queries on `devices` table when steady-state online
        $this->assertCount(0, $deviceUpdateQueries, 'Steady-state heartbeat must not write to devices table in MySQL.');

        // Verify local file cache holds the presence record
        $cached = Cache::store('file')->get("device_presence:{$device->id}");
        $this->assertNotNull($cached);
        $this->assertEquals(95, $cached['telemetry']['battery']);
        $this->assertTrue($cached['telemetry']['isCharging']);
    }

    public function test_heartbeat_transitions_offline_device_to_online(): void
    {
        [$device, $token] = $this->createDevice('TAB-001', 'offline');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/device/heartbeat', [
                'action' => 'HEARTBEAT',
                'device_id' => 'TAB-001',
                'timestamp' => now()->timestamp,
                'telemetry' => ['battery' => 88],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'online']);

        $device->refresh();
        $this->assertEquals('online', $device->status);
    }

    public function test_presence_sweeper_transitions_stale_device_to_warning_then_offline(): void
    {
        [$device] = $this->createDevice('TAB-001', 'online');
        $presenceService = app(DevicePresenceService::class);

        // Simulate heartbeat age = 85 seconds (Between 75s and 105s -> WARNING)
        $staleHeartbeatTime = now()->timestamp - 85;
        Cache::store('file')->put("device_presence:{$device->id}", [
            'device_id' => $device->id,
            'uuid' => $device->uuid,
            'timestamp' => $staleHeartbeatTime,
        ], 300);

        $results = $presenceService->sweepPresence();

        $this->assertEquals(1, $results['transitions_count']);
        $this->assertEquals('warning', $results['transitions'][0]['to']);

        $device->refresh();
        $this->assertEquals('warning', $device->status);

        // Simulate heartbeat age = 115 seconds (> 105s -> OFFLINE)
        $expiredHeartbeatTime = now()->timestamp - 115;
        Cache::store('file')->put("device_presence:{$device->id}", [
            'device_id' => $device->id,
            'uuid' => $device->uuid,
            'timestamp' => $expiredHeartbeatTime,
        ], 300);

        $results2 = $presenceService->sweepPresence();

        $this->assertEquals(1, $results2['transitions_count']);
        $this->assertEquals('offline', $results2['transitions2']['transitions'][0]['to'] ?? $results2['transitions'][0]['to']);

        $device->refresh();
        $this->assertEquals('offline', $device->status);
    }

    public function test_presence_sweeper_avoids_repeated_writes_when_status_is_unchanged(): void
    {
        [$device] = $this->createDevice('TAB-001', 'offline');
        $device->update(['last_seen_at' => now()->subMinutes(10)]);
        $presenceService = app(DevicePresenceService::class);

        // Device is already offline and has no recent heartbeat
        $results = $presenceService->sweepPresence();

        $this->assertEquals(0, $results['transitions_count']);
    }

    public function test_sweep_presence_command_executes(): void
    {
        $this->artisan('monitor:sweep-presence')
            ->expectsOutputToContain('Presence sweep completed')
            ->assertExitCode(0);
    }
}
