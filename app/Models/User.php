<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'username',
        'password',
        'fullname',
        'nik',
        'phone',
        'address',
        'join_date',
        'role_id',
        'basic_salary',
        'bank_account',
        'signature_path',
        'avatar_path',
        'face_reference_path',
        'employee_status',
        'department',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'created_at' => 'datetime',
            'basic_salary' => 'decimal:2',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role?->role_slug === 'admin') {
            return true;
        }

        return $this->role
            ? $this->role->permissions()->where('permission_slug', $permission)->exists()
            : false;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }
}
