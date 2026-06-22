<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTenantScope;

class Role extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'permissions',
        'data_scope',
    ];

    protected $casts = [
        'permissions' => 'array',
        'data_scope' => 'integer',
    ];

    protected static $tenantColumn = 'tenant_id';

    public function users()
    {
        return $this->hasMany(User::class, 'role_code', 'code');
    }

    public function departments()
    {
        return $this->belongsToMany(Dept::class, 'sys_role_dept', 'role_id', 'dept_id');
    }

    public function isAllDataPermission(): bool
    {
        return ($this->data_scope ?? 0) === \App\Enums\DataScopeEnum::ALL;
    }
}
