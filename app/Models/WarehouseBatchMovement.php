<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseBatchMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'batch_id',
        'movement_date',
        'movement_type',
        'qty',
        'ref_doc',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'qty' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WarehouseBatch::class, 'batch_id');
    }
}
