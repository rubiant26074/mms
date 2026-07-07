<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'assignment_id',
        'activity',
        'log_time',
        'operator_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_time' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductionLog $log): void {
            $log->log_time ??= now();
        });
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProductionAssignment::class, 'assignment_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
