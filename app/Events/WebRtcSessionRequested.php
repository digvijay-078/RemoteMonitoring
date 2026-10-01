<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRtcSessionRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $desktopUuid,
        public string $sessionId,
        public string $tabletUuid,
        public string $tabletIdentifier,
        public string $tabletName,
        public array $iceServers
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('device.' . $this->desktopUuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webrtc.session.requested';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => 'WEBRTC_SESSION_REQUESTED',
            'session_id' => $this->sessionId,
            'tablet_uuid' => $this->tabletUuid,
            'tablet_identifier' => $this->tabletIdentifier,
            'tablet_name' => $this->tabletName,
            'ice_servers' => $this->iceServers,
            'timestamp' => now()->timestamp,
        ];
    }
}
