<?php

namespace Tests\Feature;

use App\Events\WebRtcAnswerReceived;
use App\Events\WebRtcIceCandidateReceived;
use App\Events\WebRtcOfferReceived;
use App\Events\WebRtcSessionRequested;
use App\Events\WebRtcSessionStatusChanged;
use App\Models\DesktopTabletMapping;
use App\Models\Device;
use App\Models\DeviceCredential;
use App\Models\WebRtcSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebRtcSignallingTest extends TestCase
{
    use RefreshDatabase;

    protected Device $tablet;
    protected string $tabletToken;
    protected Device $desktop;
    protected string $desktopToken;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tablet, $this->tabletToken] = $this->createDeviceWithToken('TAB-001', 'tablet');
        [$this->desktop, $this->desktopToken] = $this->createDeviceWithToken('DESK-001', 'desktop');
    }

    protected function createDeviceWithToken(string $identifier, string $type = 'tablet'): array
    {
        $device = Device::create([
            'uuid' => (string) Str::uuid(),
            'device_identifier' => $identifier,
            'name' => "Device {$identifier}",
            'location' => 'HQ Lab',
            'device_type' => $type,
            'status' => 'online',
            'stream_status' => 'idle',
        ]);

        $plainToken = bin2hex(random_bytes(32));
        DeviceCredential::create([
            'device_id' => $device->id,
            'token_prefix' => substr($plainToken, 0, 8),
            'token_hash' => hash('sha256', $plainToken),
            'last_rotated_at' => now(),
        ]);

        return [$device, $plainToken];
    }

    public function test_tablet_can_fetch_assigned_desktops(): void
    {
        // Without mapping, list is empty
        $res = $this->withHeader('Authorization', "Bearer {$this->tabletToken}")
            ->getJson('/api/v1/tablet/desktops');

        $res->assertStatus(200);
        $res->assertJsonCount(0, 'desktops');

        // Create mapping
        DesktopTabletMapping::create([
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $res2 = $this->withHeader('Authorization', "Bearer {$this->tabletToken}")
            ->getJson('/api/v1/tablet/desktops');

        $res2->assertStatus(200);
        $res2->assertJsonCount(1, 'desktops');
        $res2->assertJsonFragment([
            'device_identifier' => 'DESK-001',
            'uuid' => $this->desktop->uuid,
        ]);
    }

    public function test_tablet_cannot_initiate_session_with_unmapped_desktop(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->tabletToken}")
            ->postJson('/api/v1/webrtc/session/initiate', [
                'desktop_uuid' => $this->desktop->uuid,
            ]);

        $response->assertStatus(403);
    }

    public function test_tablet_can_initiate_session_with_authorized_desktop(): void
    {
        Event::fake([WebRtcSessionRequested::class]);

        DesktopTabletMapping::create([
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->tabletToken}")
            ->postJson('/api/v1/webrtc/session/initiate', [
                'desktop_uuid' => $this->desktop->uuid,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'session_id',
            'desktop_uuid',
            'ice_servers',
        ]);

        $sessionId = $response->json('session_id');

        $this->assertDatabaseHas('webrtc_sessions', [
            'session_id' => $sessionId,
            'desktop_id' => $this->desktop->id,
            'tablet_id' => $this->tablet->id,
            'status' => 'initiating',
        ]);

        Event::assertDispatched(WebRtcSessionRequested::class, function ($event) use ($sessionId) {
            return $event->sessionId === $sessionId && $event->desktopUuid === $this->desktop->uuid;
        });
    }

    public function test_desktop_can_relay_sdp_offer(): void
    {
        Event::fake([WebRtcOfferReceived::class]);

        DesktopTabletMapping::create([
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $sessionId = (string) Str::uuid();
        WebRtcSession::create([
            'session_id' => $sessionId,
            'desktop_id' => $this->desktop->id,
            'tablet_id' => $this->tablet->id,
            'status' => 'initiating',
            'started_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->desktopToken}")
            ->postJson('/api/v1/webrtc/signal/offer', [
                'session_id' => $sessionId,
                'sdp' => [
                    'type' => 'offer',
                    'sdp' => 'v=0\r\no=- 12345 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n',
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'relayed_to' => $this->tablet->uuid]);

        Event::assertDispatched(WebRtcOfferReceived::class, function ($event) use ($sessionId) {
            return $event->sessionId === $sessionId && $event->tabletUuid === $this->tablet->uuid;
        });
    }

    public function test_tablet_can_relay_sdp_answer(): void
    {
        Event::fake([WebRtcAnswerReceived::class]);

        DesktopTabletMapping::create([
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $sessionId = (string) Str::uuid();
        WebRtcSession::create([
            'session_id' => $sessionId,
            'desktop_id' => $this->desktop->id,
            'tablet_id' => $this->tablet->id,
            'status' => 'initiating',
            'started_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->tabletToken}")
            ->postJson('/api/v1/webrtc/signal/answer', [
                'session_id' => $sessionId,
                'sdp' => [
                    'type' => 'answer',
                    'sdp' => 'v=0\r\no=- 67890 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\n',
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'relayed_to' => $this->desktop->uuid]);

        Event::assertDispatched(WebRtcAnswerReceived::class, function ($event) use ($sessionId) {
            return $event->sessionId === $sessionId && $event->desktopUuid === $this->desktop->uuid;
        });
    }

    public function test_peers_can_relay_ice_candidates(): void
    {
        Event::fake([WebRtcIceCandidateReceived::class]);

        DesktopTabletMapping::create([
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $sessionId = (string) Str::uuid();
        WebRtcSession::create([
            'session_id' => $sessionId,
            'desktop_id' => $this->desktop->id,
            'tablet_id' => $this->tablet->id,
            'status' => 'initiating',
            'started_at' => now(),
        ]);

        // Tablet sends candidate -> routed to Desktop
        $response = $this->withHeader('Authorization', "Bearer {$this->tabletToken}")
            ->postJson('/api/v1/webrtc/signal/ice-candidate', [
                'session_id' => $sessionId,
                'candidate' => [
                    'candidate' => 'candidate:1 1 UDP 2130706431 192.168.1.100 50000 typ host',
                    'sdpMid' => '0',
                    'sdpMLineIndex' => 0,
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'relayed_to' => $this->desktop->uuid]);

        Event::assertDispatched(WebRtcIceCandidateReceived::class, function ($event) use ($sessionId) {
            return $event->sessionId === $sessionId && $event->targetUuid === $this->desktop->uuid;
        });
    }

    public function test_session_status_updates_active_viewers_and_stream_status(): void
    {
        Event::fake([WebRtcSessionStatusChanged::class]);

        DesktopTabletMapping::create([
            'tablet_id' => $this->tablet->id,
            'desktop_id' => $this->desktop->id,
        ]);

        $sessionId = (string) Str::uuid();
        $session = WebRtcSession::create([
            'session_id' => $sessionId,
            'desktop_id' => $this->desktop->id,
            'tablet_id' => $this->tablet->id,
            'status' => 'initiating',
            'started_at' => now(),
        ]);

        // Tablet reports connected
        $resConnect = $this->withHeader('Authorization', "Bearer {$this->tabletToken}")
            ->postJson('/api/v1/webrtc/session/status', [
                'session_id' => $sessionId,
                'status' => 'connected',
            ]);

        $resConnect->assertStatus(200);
        $resConnect->assertJson([
            'status' => 'connected',
            'active_viewers_count' => 1,
            'stream_status' => 'streaming',
        ]);

        $this->assertEquals('streaming', $this->desktop->fresh()->stream_status);
        $this->assertEquals(1, $this->desktop->fresh()->active_viewers_count);

        // Tablet reports terminated
        $resTerm = $this->withHeader('Authorization', "Bearer {$this->tabletToken}")
            ->postJson('/api/v1/webrtc/session/status', [
                'session_id' => $sessionId,
                'status' => 'terminated',
                'reason' => 'User closed viewer',
            ]);

        $resTerm->assertStatus(200);
        $resTerm->assertJson([
            'status' => 'terminated',
            'active_viewers_count' => 0,
            'stream_status' => 'idle',
        ]);

        $this->assertEquals('idle', $this->desktop->fresh()->stream_status);
        $this->assertEquals(0, $this->desktop->fresh()->active_viewers_count);
    }
}
