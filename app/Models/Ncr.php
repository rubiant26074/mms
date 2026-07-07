<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ncr extends Model
{
    protected $table = 'ncr';
    public const UPDATED_AT = null;

    protected $fillable = [
        'ncr_number',
        'source_type',
        'reference_id',
        'item_id',
        'qty_reject',
        'issue_description',
        'root_cause',
        'corrective_action',
        'operator_id',
        'disposition',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty_reject' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
