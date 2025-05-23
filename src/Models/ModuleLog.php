<?php

namespace Egerstudios\TenantModules\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'module_name',
        'action',
        'occurred_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'occurred_at' => 'datetime',
    ];
} 