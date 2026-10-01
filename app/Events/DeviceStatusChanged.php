<?php

namespace App\Events;

use App\Models\Device;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $deviceId;
    public string $uuid;
    public string $identifier;
    public string $name;
    public string $location;
    public string $status;
    public ?string $lastSeenAt;
    public string $lastSeenHuman;

    /**
     * Create a new event instance.
     */
    public function __construct(Device $device)
    {
        $this->deviceId = $device->id;
        $this->uuid = $device->uuid;
        $this->identifier = $device->device_identifier;
        $this->name = $device->name;
        $this->location = $device->location;
        $this->status = $device->status;
        $this->lastSeenAt = $device->last_seen_at ? $device->last_seen_at->toIso8601String() : null;
        $this->lastSeenHuman = $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never';
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.devices'),
            new Channel('admin.devices.public'),
        ];
    }

    /**
     * Broadcast with custom event name.
     */
    public function broadcastAs(): string
    {
        return 'DeviceStatusChanged';
    }

    /**
     * Payload for admin dashboard.
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'uuid' => $this->uuid,
            'identifier' => $this->identifier,
            'name' => $this->name,
            'location' => $this->location,
            'status' => $this->status,
            'last_seen_at' => $this->lastSeenAt,
            'last_seen_human' => $this->lastSeenHuman,
            'timestamp' => now()->timestamp,
        ];
    }
}
