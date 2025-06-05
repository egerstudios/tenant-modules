<?php

namespace Egerstudios\TenantModules\Events;

use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;

class ModuleDisabled extends ModuleStateChanged
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Tenant $tenant,
        public Module $module,
        public array $moduleData = []
    ) {
        parent::__construct($tenant, $module, $moduleData);
    }
} 