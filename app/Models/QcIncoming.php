<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QcIncoming extends Model
{
    protected $table = 'qc_incoming';
    public const UPDATED_AT = null;

    protected $fillable = [
        'qc_number',
        'goods_receipt_id',
        'qc_date',
        'inspector_id',
        'approved_by',
        'approved_at',
        'handover_by',
        'handover_at',
        'status',
        'final_decision',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qc_date' => 'date',
            'approved_at' => 'datetime',
            'handover_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QcIncomingItem::class, 'qc_incoming_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function handoverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_by');
    }
}
