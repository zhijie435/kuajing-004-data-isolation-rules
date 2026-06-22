<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\HasTenantScope;
use App\Enums\DataScopeEnum;

class User extends Authenticatable
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'dept_id',
        'username',
        'nickname',
        'email',
        'password',
        'role_code',
        'data_scope',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'data_scope' => 'integer',
        'status' => 'boolean',
    ];

    protected static $tenantColumn = 'tenant_id';

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_code', 'code');
    }

    public function department()
    {
        return $this->belongsTo(Dept::class, 'dept_id', 'id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSuperAdmin(): bool
    {
        return in_array($this->role_code, ['super_admin', 'system_admin']);
    }

    public function getDataScopeLabelAttribute(): string
    {
        return DataScopeEnum::label($this->data_scope ?? DataScopeEnum::SELF);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        $role = $this->role;
        if (!$role) {
            return false;
        }

        if ($role->isAllDataPermission()) {
            return true;
        }

        return in_array($permission, $role->permissions ?? []);
    }
}
