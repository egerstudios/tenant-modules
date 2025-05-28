<?php

namespace Egerstudios\TenantModules\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Egerstudios\TenantModules\Models\Module;

trait HasModules
{
    /**
     * Get all modules associated with the tenant.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tenant_modules')
            ->using(\Egerstudios\TenantModules\Models\TenantModule::class)
            ->withPivot('is_active', 'activated_at', 'deactivated_at', 'settings', 'last_billed_at', 'billing_cycle')
            ->withTimestamps();
    }

    /**
     * Get only active modules for the tenant.
     */
    public function activeModules(): BelongsToMany
    {
        return $this->modules()
            ->wherePivot('is_active', true);
    }

    /**
     * Get a list of active module names.
     */
    public function listActiveModules(): array
    {
        return $this->activeModules()->toArray();
    }

    /**
     * Check if the tenant has a specific module active.
     */
    public function hasModule(string $moduleName): bool
    {
        return $this->activeModules()
            ->where('name', $moduleName)
            ->exists();
    }

    /**
     * Get the tenant's module with its settings.
     */
    public function getModule(string $moduleName)
    {
        return $this->modules()
            ->where('name', $moduleName)
            ->first();
    }

    /**
     * Get true/false on module existence
     * @return bool
     */
    public function hasModules(): bool
    {
        return $this->modules()->exists();
    }
} 