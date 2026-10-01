<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesktopTabletMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'tablet_id',
        'desktop_id',
        'created_by',
    ];

    public function tablet(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'tablet_id');
    }

    public function desktop(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'desktop_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
