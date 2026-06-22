<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'code',
        'domain',
        'status',
        'expire_at',
        'max_users',
    ];

    protected $casts = [
        'status' => 'boolean',
        'expire_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function isExpired(): bool
    {
        return $this->expire_at && $this->expire_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status && !$this->isExpired();
    }
}
