<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRtcAnswerReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $desktopUuid,
        public string $sessionId,
        public string $tabletUuid,
        public array $sdp
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('device.' . $this->desktopUuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webrtc.signal.answer';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => 'WEBRTC_SIGNAL_ANSWER',
            'session_id' => $this->sessionId,
            'tablet_uuid' => $this->tabletUuid,
            'sdp' => $this->sdp,
            'timestamp' => now()->timestamp,
        ];
    }
}
