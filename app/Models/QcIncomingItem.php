<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcIncomingItem extends Model
{
    protected $table = 'qc_incoming_items';
    public $timestamps = false;

    protected $fillable = [
        'qc_incoming_id',
        'item_id',
        'qty_received',
        'qty_good',
        'qty_reject',
        'checklist_data',
        'notes',
    ];

    public function qcIncoming(): BelongsTo
    {
        return $this->belongsTo(QcIncoming::class, 'qc_incoming_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
