<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRtcIceCandidateReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $targetUuid,
        public string $sessionId,
        public string $senderUuid,
        public array $candidate
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('device.' . $this->targetUuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'webrtc.signal.ice_candidate';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => 'WEBRTC_SIGNAL_ICE_CANDIDATE',
            'session_id' => $this->sessionId,
            'sender_uuid' => $this->senderUuid,
            'candidate' => $this->candidate,
            'timestamp' => now()->timestamp,
        ];
    }
}
