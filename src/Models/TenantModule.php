<?php

namespace Egerstudios\TenantModules\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantModule extends Pivot
{
    protected $table = 'tenant_modules';

    protected $fillable = [
        'is_active',
        'activated_at',
        'deactivated_at',
        'settings',
        'last_billed_at',
        'billing_cycle',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'last_billed_at' => 'datetime',
        'settings' => 'json',
    ];
} 