<?php

namespace Egerstudios\TenantModules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Tenant;

class Module extends Model
{
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
} 