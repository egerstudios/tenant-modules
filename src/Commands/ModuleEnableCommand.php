<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;
use Egerstudios\TenantModules\Models\ModuleLog;

class ModuleEnableCommand extends Command
{
    protected $signature = 'module:enable {module} {--domain=}';
    protected $description = 'Enable a module for a tenant';

    public function handle()
    {
        $moduleName = $this->argument('module');
        $domain = $this->option('domain');

        if (!$domain) {
            $this->error('Domain is required. Use --domain option.');
            return 1;
        }

        // Find the tenant
        $tenant = Tenant::whereHas('domains', function ($query) use ($domain) {
            $query->where('domain', $domain);
        })->first();
        if (!$tenant) {
            $this->error("Tenant with domain {$domain} not found.");
            return 1;
        }

        // Find or create the module
        $module = Module::firstOrCreate(
            ['name' => $moduleName],
            [
                'description' => "Module {$moduleName}",
                'version' => '1.0.0',
                'is_core' => false
            ]
        );

        // Enable the module for the tenant
        $tenant->modules()->syncWithoutDetaching([
            $module->id => [
                'is_active' => true,
                'activated_at' => now(),
                'deactivated_at' => null
            ]
        ]);

        // Log the action
        ModuleLog::create([
            'tenant_id' => $tenant->id,
            'module_name' => $moduleName,
            'action' => 'enabled',
            'occurred_at' => now()
        ]);

        $this->info("Module {$moduleName} has been enabled for tenant {$domain}.");
        return 0;
    }
} 