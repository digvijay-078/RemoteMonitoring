<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebRtcSession extends Model
{
    use HasFactory;

    protected $table = 'webrtc_sessions';

    protected $fillable = [
        'session_id',
        'desktop_id',
        'tablet_id',
        'status',
        'started_at',
        'connected_at',
        'ended_at',
        'termination_reason',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'connected_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function desktop(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'desktop_id');
    }

    public function tablet(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'tablet_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['initiating', 'connected']);
    }

    public function getDurationSecondsAttribute(): int
    {
        $start = $this->connected_at ?: $this->started_at;
        if (!$start) return 0;
        $end = $this->ended_at ?: now();
        return max(0, $start->diffInSeconds($end));
    }

    public function getDurationHumanAttribute(): string
    {
        if ($this->status === 'connected' && !$this->ended_at) {
            $secs = $this->duration_seconds;
            return $this->formatSeconds($secs) . ' (Active Now 🟢)';
        }

        if (!$this->ended_at && $this->status === 'initiating') {
            return 'Connecting...';
        }

        return $this->formatSeconds($this->duration_seconds);
    }

    protected function formatSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }
        $mins = floor($seconds / 60);
        $remSecs = $seconds % 60;
        if ($mins < 60) {
            return "{$mins}m {$remSecs}s";
        }
        $hrs = floor($mins / 60);
        $remMins = $mins % 60;
        return "{$hrs}h {$remMins}m {$remSecs}s";
    }

    public function getStartedAtIstAttribute(): ?string
    {
        return $this->started_at ? $this->started_at->timezone('Asia/Kolkata')->format('d-m-Y h:i:s A') : null;
    }

    public function getConnectedAtIstAttribute(): ?string
    {
        return $this->connected_at ? $this->connected_at->timezone('Asia/Kolkata')->format('d-m-Y h:i:s A') : null;
    }

    public function getEndedAtIstAttribute(): ?string
    {
        return $this->ended_at ? $this->ended_at->timezone('Asia/Kolkata')->format('d-m-Y h:i:s A') : null;
    }
}
