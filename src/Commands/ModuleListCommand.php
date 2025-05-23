<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list {--domain=}';
    protected $description = 'List all modules and their status for a tenant';

    public function handle()
    {
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

        // Get all modules with their status for this tenant
        $modules = Module::with(['tenants' => function ($query) use ($tenant) {
            $query->where('tenants.id', $tenant->id);
        }])->get();

        $this->info("\nModules for tenant {$domain}:");
        $this->table(
            ['Module', 'Version', 'Status', 'Activated At', 'Last Billed'],
            $modules->map(function ($module) {
                $tenantModule = $module->tenants->first();
                return [
                    $module->name,
                    $module->version,
                    $tenantModule && $tenantModule->pivot->is_active ? 'Active' : 'Inactive',
                    $tenantModule ? $tenantModule->pivot->activated_at?->format('Y-m-d H:i:s') : 'N/A',
                    $tenantModule ? $tenantModule->pivot->last_billed_at?->format('Y-m-d H:i:s') : 'N/A',
                ];
            })
        );

        return 0;
    }
} 