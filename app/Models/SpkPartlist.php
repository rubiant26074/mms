<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpkPartlist extends Model
{
    protected $table = 'spk_partlists';

    public $timestamps = false;

    protected $fillable = [
        'spk_id',
        'item_no',
        'drawing_no',
        'part_name',
        'qty',
        'material',
        'thickness',
        'length',
        'width',
        'process',
        'notes',
    ];

    public function spk(): BelongsTo
    {
        return $this->belongsTo(Spk::class, 'spk_id');
    }
}
