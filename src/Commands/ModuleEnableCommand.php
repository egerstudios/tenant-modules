<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;
use Egerstudios\TenantModules\Models\ModuleLog;
use Egerstudios\TenantModules\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use Egerstudios\TenantModules\Events\ModuleEnabled;

/**
 * ModuleEnableCommand - Clean command that delegates to ModuleManager
 */
class ModuleEnableCommand extends Command
{
    protected $signature = 'module:enable {module} {--domain=}';
    protected $description = 'Enable a module for a tenant';

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

        // Check if already enabled
        if ($moduleManager->isModuleEnabled($tenant, $moduleName)) {
            $this->info("Module {$moduleName} is already enabled for tenant {$domain}.");
            return 0;
        }

        // Enable the module
        $this->info("Enabling module {$moduleName} for tenant {$domain}...");
        
        try {
            if ($moduleManager->enableModule($tenant, $moduleName)) {
                $this->info("✅ Module {$moduleName} has been enabled for tenant {$domain}.");
                return 0;
            } else {
                $this->error("❌ Failed to enable module {$moduleName} for tenant {$domain}. Check the logs for details.");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Error enabling module {$moduleName} for tenant {$domain}: {$e->getMessage()}");
            return 1;
        }
    }

    protected function updateUserPermissions(Tenant $tenant, string $moduleName): void
    {
        // Get all users associated with the tenant
        $users = $tenant->users;

        // Run in tenant context to update permissions
        $tenant->run(function () use ($users, $moduleName) {
            // First ensure permission tables exist
            if (!Schema::hasTable('permissions')) {
                $this->info("Creating permission tables...");
                Artisan::call('vendor:publish', [
                    '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                    '--tag' => 'migrations'
                ]);
                Artisan::call('migrate', ['--force' => true]);
            }

            // Get all module permissions
            $modulePermissions = Permission::where('name', 'like', "{$moduleName}.%")->get();

            // Map existing roles to module permissions
            $rolePermissionMap = [
                'Owner' => $modulePermissions->pluck('name')->toArray(), // Full access
                'Admin' => $modulePermissions->pluck('name')->toArray(), // Full access
                'Manager' => array_filter($modulePermissions->pluck('name')->toArray(), function($permission) {
                    return !str_ends_with($permission, '.delete') && !str_ends_with($permission, '.manage');
                }),
                'Member' => array_filter($modulePermissions->pluck('name')->toArray(), function($permission) {
                    return str_ends_with($permission, '.view');
                }),
            ];

            foreach ($users as $user) {
                // Get user's current roles
                $currentRoles = $user->getRoleNames();

                // Assign module permissions based on existing roles
                foreach ($currentRoles as $roleName) {
                    if (isset($rolePermissionMap[$roleName])) {
                        $user->givePermissionTo($rolePermissionMap[$roleName]);
                    }
                }

                // Additionally assign module-specific roles for better organization
                if ($currentRoles->contains('Owner') || $currentRoles->contains('Admin')) {
                    $user->assignRole($moduleName . '-manager');
                } else {
                    $user->assignRole($moduleName . '-user');
                }
            }
        });
    }
}