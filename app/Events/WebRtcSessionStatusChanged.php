<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebRtcSessionStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $targetUuid,
        public string $sessionId,
        public string $desktopUuid,
        public string $tabletUuid,
        public string $status,
        public ?string $reason = null,
        public int $activeViewersCount = 0,
        public string $streamStatus = 'idle'
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('admin.devices'),
        ];

        if (!empty($this->targetUuid)) {
            $channels[] = new PrivateChannel('device.' . $this->targetUuid);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'webrtc.session.status';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => 'WEBRTC_SESSION_STATUS_CHANGED',
            'session_id' => $this->sessionId,
            'desktop_uuid' => $this->desktopUuid,
            'tablet_uuid' => $this->tabletUuid,
            'status' => $this->status,
            'reason' => $this->reason,
            'active_viewers_count' => $this->activeViewersCount,
            'stream_status' => $this->streamStatus,
            'timestamp' => now()->timestamp,
        ];
    }
}
