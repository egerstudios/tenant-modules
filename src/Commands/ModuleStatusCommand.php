<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Egerstudios\TenantModules\Services\ModuleManager;

/**
 * ModuleStatusCommand - Show module status for a tenant
 */
class ModuleStatusCommand extends Command
{
    protected $signature = 'module:status {--domain=} {--module=}';
    protected $description = 'Show module status for a tenant';

    public function handle(ModuleManager $moduleManager): int
    {
        $domain = $this->option('domain');
        $moduleName = $this->option('module');

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

        if ($moduleName) {
            // Show status for specific module
            $isEnabled = $moduleManager->isModuleEnabled($tenant, $moduleName);
            $status = $isEnabled ? '✅ Enabled' : '❌ Disabled';
            $this->info("Module {$moduleName}: {$status}");
        } else {
            // Show all enabled modules
            $enabledModules = $moduleManager->getEnabledModules($tenant);
            
            if (empty($enabledModules)) {
                $this->info("No modules are currently enabled for tenant {$domain}.");
            } else {
                $this->info("Enabled modules for tenant {$domain}:");
                foreach ($enabledModules as $module) {
                    $this->line("  ✅ {$module}");
                }
            }
        }

        return 0;
    }
}