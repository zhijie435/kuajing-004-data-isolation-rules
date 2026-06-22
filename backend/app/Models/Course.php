<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTenantScope;
use App\Traits\HasDataScope;

class Course extends Model
{
    use HasTenantScope, HasDataScope;

    protected $fillable = [
        'tenant_id',
        'dept_id',
        'created_by',
        'title',
        'description',
        'category_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected static $tenantColumn = 'tenant_id';
    protected static $dataScopeUserIdColumn = 'created_by';
    protected static $dataScopeDeptIdColumn = 'dept_id';
    protected static $dataScopeTenantIdColumn = 'tenant_id';

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Dept::class, 'dept_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(CourseCategory::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
