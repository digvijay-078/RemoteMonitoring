<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePairingTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'pairing_code',
        'pairing_token',
        'status',
        'expires_at',
        'attempt_count',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }
}
