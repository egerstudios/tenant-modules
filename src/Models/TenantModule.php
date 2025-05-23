<?php

namespace Egerstudios\TenantModules\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantModule extends Pivot
{
    protected $table = 'tenant_modules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'module_id',
        'is_active',
        'activated_at',
        'deactivated_at',
        'settings',
        'last_billed_at',
        'billing_cycle',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'settings' => 'json',
        'last_billed_at' => 'datetime',
    ];

    /**
     * Get the module that owns this tenant module.
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the tenant that owns this tenant module.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Calculate the duration this module has been active.
     */
    public function getActiveDuration()
    {
        if (!$this->is_active) {
            return $this->activated_at->diffInSeconds($this->deactivated_at);
        }

        return $this->activated_at->diffInSeconds(now());
    }
} 