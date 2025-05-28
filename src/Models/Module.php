<?php

namespace Egerstudios\TenantModules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Tenant;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Module extends Model
{
    use HasPermissions;
    use HasRoles;

    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'description',
        'version',
        'is_core',
        'settings_schema',
    ];

    protected $casts = [
        'is_core' => 'boolean',
        'settings_schema' => 'json',
    ];

    /**
     * Get the tenants that have this module.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_modules')
            ->using(TenantModule::class)
            ->withPivot('is_active', 'activated_at', 'deactivated_at', 'settings', 'last_billed_at', 'billing_cycle')
            ->withTimestamps();
    }

    /**
     * Get only active tenants for this module.
     */
    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()
            ->wherePivot('is_active', true);
    }

    /**
     * Get all permissions associated with this module.
     */
    public function getModulePermissions()
    {
        $moduleName = strtolower($this->name);
        return Permission::where('name', 'like', "{$moduleName}.%")->get();
    }

    /**
     * Get all roles associated with this module.
     */
    public function getModuleRoles()
    {
        $moduleName = strtolower($this->name);
        return Role::where('name', 'like', "{$moduleName}-%")->get();
    }
} 