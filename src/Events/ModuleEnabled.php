<?php

namespace Egerstudios\TenantModules\Events;

use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;

class ModuleEnabled extends ModuleStateChanged
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Tenant $tenant,
        public string $moduleName,
        public array $moduleData = []
    ) {
        // Find or create the module
        $module = Module::firstOrCreate(
            ['name' => $moduleName],
            [
                'description' => "Module {$moduleName}",
                'version' => '1.0.0',
                'is_core' => false
            ]
        );

        // Initialize parent with the Module object
        parent::__construct($tenant, $module, $moduleData);
    }
} 