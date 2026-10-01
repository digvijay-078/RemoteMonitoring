<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'token_prefix',
        'token_hash',
        'revoked_at',
        'last_used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }
}
