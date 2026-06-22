<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasTenantScope;

class Dept extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'code',
        'sort',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected static $tenantColumn = 'tenant_id';

    public function parent()
    {
        return $this->belongsTo(Dept::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Dept::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function getAllChildrenIds(): array
    {
        $ids = [];
        $children = $this->children;
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllChildrenIds());
        }
        return $ids;
    }

    public function getSelfAndChildrenIds(): array
    {
        return array_merge([$this->id], $this->getAllChildrenIds());
    }
}
