<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialIssueItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'material_issue_id',
        'item_id',
        'qty_issued',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_issued' => 'decimal:4',
        ];
    }

    public function materialIssue(): BelongsTo
    {
        return $this->belongsTo(MaterialIssue::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
