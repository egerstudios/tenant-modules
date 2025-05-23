<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;
use Egerstudios\TenantModules\Models\ModuleLog;

class ModuleDisableCommand extends Command
{
    protected $signature = 'module:disable {module} {--domain=}';
    protected $description = 'Disable a module for a tenant';

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

        // Find the module
        $module = Module::where('name', $moduleName)->first();
        if (!$module) {
            $this->error("Module {$moduleName} not found.");
            return 1;
        }

        // Update the pivot table to disable the module
        $tenant->modules()->updateExistingPivot($module->id, [
            'is_active' => false,
            'deactivated_at' => now()
        ]);

        // Log the action
        ModuleLog::create([
            'tenant_id' => $tenant->id,
            'module_name' => $moduleName,
            'action' => 'disabled',
            'occurred_at' => now()
        ]);

        $this->info("Module {$moduleName} has been disabled for tenant {$domain}.");
        return 0;
    }
} 