<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MmsNotification extends Model
{
    protected $table = 'notifications';

    public const UPDATED_AT = null;

    protected $fillable = [
        'sender_id',
        'user_id',
        'title',
        'target_role',
        'message',
        'link',
        'type',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
