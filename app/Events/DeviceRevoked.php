<?php

namespace App\Events;

use App\Models\Device;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceRevoked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $uuid;
    public string $identifier;
    public string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Device $device, string $reason = 'ADMIN_ACTION')
    {
        $this->uuid = $device->uuid;
        $this->identifier = $device->device_identifier;
        $this->reason = $reason;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('device.' . $this->uuid),
        ];
    }

    /**
     * Event name for client binding.
     */
    public function broadcastAs(): string
    {
        return 'DeviceRevoked';
    }

    /**
     * Payload for tablet client.
     */
    public function broadcastWith(): array
    {
        return [
            'action' => 'DEVICE_REVOKED',
            'uuid' => $this->uuid,
            'identifier' => $this->identifier,
            'reason' => $this->reason,
            'timestamp' => now()->timestamp,
        ];
    }
}
