<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'device_type',
        'device_identifier',
        'name',
        'location',
        'status',
        'stream_status',
        'active_viewers_count',
        'screen_resolution',
        'fps',
        'ip_address',
        'user_agent',
        'hardware_info',
        'paired_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'hardware_info' => 'array',
            'paired_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'active_viewers_count' => 'integer',
            'fps' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Device $device) {
            if (empty($device->uuid)) {
                $device->uuid = (string) Str::uuid();
            }
            if (empty($device->device_type)) {
                $device->device_type = 'tablet';
            }
            if (empty($device->stream_status)) {
                $device->stream_status = 'offline';
            }
        });
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(DeviceCredential::class);
    }

    public function activeCredential(): HasOne
    {
        return $this->hasOne(DeviceCredential::class)->whereNull('revoked_at')->latestOfMany();
    }

    public function pairingTickets(): HasMany
    {
        return $this->hasMany(DevicePairingTicket::class);
    }

    public function latestActivePairingTicket(): HasOne
    {
        return $this->hasOne(DevicePairingTicket::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latestOfMany();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(DeviceAuditLog::class)->orderByDesc('created_at');
    }

    /**
     * For a Tablet: Desktops assigned to be viewed by this tablet.
     */
    public function assignedDesktops(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'desktop_tablet_mappings', 'tablet_id', 'desktop_id')
            ->withTimestamps();
    }

    /**
     * For a Desktop: Tablets authorized to view this desktop.
     */
    public function assignedTablets(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'desktop_tablet_mappings', 'desktop_id', 'tablet_id')
            ->withTimestamps();
    }

    /**
     * WebRTC sessions where this device is the Desktop (stream source).
     */
    public function webrtcSessionsAsDesktop(): HasMany
    {
        return $this->hasMany(WebRtcSession::class, 'desktop_id');
    }

    /**
     * WebRTC sessions where this device is the Tablet (viewer).
     */
    public function webrtcSessionsAsTablet(): HasMany
    {
        return $this->hasMany(WebRtcSession::class, 'tablet_id');
    }

    /**
     * Currently active WebRTC viewing session for a tablet.
     */
    public function activeViewingSession(): HasOne
    {
        return $this->hasOne(WebRtcSession::class, 'tablet_id')
            ->whereIn('status', ['initiating', 'connected'])
            ->latestOfMany();
    }

    public function scopeDesktops($query)
    {
        return $query->where('device_type', 'desktop');
    }

    public function scopeTablets($query)
    {
        return $query->where('device_type', 'tablet');
    }

    public function isDesktop(): bool
    {
        return $this->device_type === 'desktop';
    }

    public function isTablet(): bool
    {
        return $this->device_type === 'tablet';
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function isStreaming(): bool
    {
        return $this->stream_status === 'streaming';
    }

    public function isPaired(): bool
    {
        return !is_null($this->paired_at) && $this->status !== 'pending_pair';
    }
}
