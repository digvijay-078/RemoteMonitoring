<?php

namespace App\Events;

use App\Models\Device;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TabletMappingChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $tabletUuid;
    public string $tabletIdentifier;
    public ?string $desktopUuid;
    public ?string $desktopIdentifier;
    public ?string $desktopName;
    public string $action;

    /**
     * Create a new event instance.
     */
    public function __construct(Device $tablet, ?Device $desktop = null, string $action = 'assigned')
    {
        $this->tabletUuid = $tablet->uuid;
        $this->tabletIdentifier = $tablet->device_identifier;
        $this->desktopUuid = $desktop?->uuid;
        $this->desktopIdentifier = $desktop?->device_identifier;
        $this->desktopName = $desktop?->name;
        $this->action = $action;
    }

    /**
     * Broadcast to the tablet's private channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('device.' . $this->tabletUuid),
            new PrivateChannel('admin.devices'),
        ];
    }

    /**
     * Custom event name.
     */
    public function broadcastAs(): string
    {
        return 'TabletMappingChanged';
    }

    /**
     * Payload for tablet viewer and admin.
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'mapping.changed',
            'action' => $this->action,
            'tablet_uuid' => $this->tabletUuid,
            'tablet_identifier' => $this->tabletIdentifier,
            'desktop' => $this->desktopUuid ? [
                'uuid' => $this->desktopUuid,
                'identifier' => $this->desktopIdentifier,
                'name' => $this->desktopName,
            ] : null,
            'timestamp' => now()->timestamp,
        ];
    }
}
