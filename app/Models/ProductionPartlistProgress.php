<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionPartlistProgress extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'production_partlist_progress';

    protected $fillable = [
        'assignment_id',
        'spk_id',
        'partlist_id',
        'qty_done',
        'progress_state',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty_done' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProductionAssignment::class, 'assignment_id');
    }

    public function partlist(): BelongsTo
    {
        return $this->belongsTo(SpkPartlist::class, 'partlist_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
