<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcProduction extends Model
{
    protected $table = 'qc_production';
    public const UPDATED_AT = null;

    protected $fillable = [
        'qc_number',
        'spk_id',
        'qc_date',
        'inspector_id',
        'approved_by',
        'status',
        'qty_check',
        'qty_pass',
        'qty_reject',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qc_date' => 'date',
            'created_at' => 'datetime',
            'qty_check' => 'decimal:2',
            'qty_pass' => 'decimal:2',
            'qty_reject' => 'decimal:2',
        ];
    }

    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class, 'spk_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
