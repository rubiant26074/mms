<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleCountSessionItem extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id',
        'item_id',
        'system_qty',
        'counted_qty',
        'variance_qty',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'float',
            'counted_qty' => 'float',
            'variance_qty' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CycleCountSession::class, 'session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
