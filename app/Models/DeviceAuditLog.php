<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'event_type',
        'severity',
        'details',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DeviceAuditLog $log) {
            if (empty($log->created_at)) {
                $log->created_at = now();
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
