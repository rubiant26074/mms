<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionAssignment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'spk_id',
        'process_name',
        'operator_id',
        'machine_id',
        'status',
        'start_time',
        'end_time',
        'qty_input',
        'qty_good',
        'qty_reject',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'created_at' => 'datetime',
            'qty_input' => 'decimal:2',
            'qty_good' => 'decimal:2',
            'qty_reject' => 'decimal:2',
        ];
    }

    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class, 'spk_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProductionLog::class, 'assignment_id');
    }

    public function partlistProgress(): HasMany
    {
        return $this->hasMany(ProductionPartlistProgress::class, 'assignment_id');
    }
}
