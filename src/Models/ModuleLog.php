<?php

namespace Egerstudios\TenantModules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'module_name',
        'action',
        'occurred_at'
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
} 