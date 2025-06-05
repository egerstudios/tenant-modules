<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;
use Egerstudios\TenantModules\Models\ModuleLog;
use Egerstudios\TenantModules\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * ModuleDisableCommand - Clean command that delegates to ModuleManager
 */
class ModuleDisableCommand extends Command
{
    protected $signature = 'module:disable {module} {--domain=}';
    protected $description = 'Disable a module for a tenant';

    public function handle(ModuleManager $moduleManager): int
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

        // Check if already disabled
        if (!$moduleManager->isModuleEnabled($tenant, $moduleName)) {
            $this->info("Module {$moduleName} is already disabled for tenant {$domain}.");
            return 0;
        }

        // Disable the module
        $this->info("Disabling module {$moduleName} for tenant {$domain}...");
        
        try {
            if ($moduleManager->disableModule($tenant, $moduleName)) {
                $this->info("✅ Module {$moduleName} has been disabled for tenant {$domain}.");
                return 0;
            } else {
                $this->error("❌ Failed to disable module {$moduleName} for tenant {$domain}. Check the logs for details.");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Error disabling module {$moduleName} for tenant {$domain}: {$e->getMessage()}");
            return 1;
        }
    }
}