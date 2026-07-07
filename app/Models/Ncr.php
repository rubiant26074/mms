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
        'resp_signed_by',
        'resp_signed_at',
        'resp_appeal_by',
        'resp_appeal_at',
        'resp_appeal_note',
        'disposition',
        'status',
        'created_by',
        'approved_by',
        'gm_approved_by',
        'gm_approved_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_reject' => 'decimal:2',
            'created_at' => 'datetime',
            'resp_signed_at' => 'datetime',
            'resp_appeal_at' => 'datetime',
            'gm_approved_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function responsibleSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resp_signed_by');
    }

    public function gmApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gm_approved_by');
    }
}
