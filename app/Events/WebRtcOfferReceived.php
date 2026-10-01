<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRtcOfferReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tabletUuid,
        public string $sessionId,
        public string $desktopUuid,
        public array $sdp,
        public array $iceServers
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('device.' . $this->tabletUuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webrtc.signal.offer';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => 'WEBRTC_SIGNAL_OFFER',
            'session_id' => $this->sessionId,
            'desktop_uuid' => $this->desktopUuid,
            'sdp' => $this->sdp,
            'ice_servers' => $this->iceServers,
            'timestamp' => now()->timestamp,
        ];
    }
}
