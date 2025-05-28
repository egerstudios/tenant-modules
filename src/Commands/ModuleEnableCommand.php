<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;
use Egerstudios\TenantModules\Models\ModuleLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Schema;

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

        // Run module migrations in tenant context
        $tenant->run(function () use ($moduleName, $tenant) {
            $this->info("Running migrations for module {$moduleName}...");
            
            // First ensure permission tables exist
            if (!Schema::hasTable('permissions')) {
                $this->info("Creating permission tables...");
                Artisan::call('vendor:publish', [
                    '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                    '--tag' => 'migrations'
                ]);
                Artisan::call('migrate', ['--force' => true]);
            }

            // Check and create module system tables if they don't exist
            if (!Schema::hasTable('tenant_modules')) {
                $this->info("Creating tenant_modules table...");
                Artisan::call('migrate', [
                    '--path' => 'tenant-modules/database/migrations/2024_03_21_000000_create_tenant_modules_table.php',
                    '--force' => true
                ]);
            }

            if (!Schema::hasTable('module_logs')) {
                $this->info("Creating module_logs table...");
                Artisan::call('migrate', [
                    '--path' => 'tenant-modules/database/migrations/2024_03_21_000001_create_module_logs_table.php',
                    '--force' => true
                ]);
            }
            
            // Then run module-specific migrations
            Artisan::call('migrate', [
                '--path' => "modules/{$moduleName}/database/migrations",
                '--force' => true
            ]);

            // Log the action
            if (Schema::hasTable('module_logs')) {
                ModuleLog::create([
                    'tenant_id' => $tenant->id,
                    'module_name' => $moduleName,
                    'action' => 'enabled',
                    'occurred_at' => now()
                ]);
            }
        });

        // Update permissions for existing users
        $this->updateUserPermissions($tenant, $moduleName);

        $this->info("Module {$moduleName} has been enabled for tenant {$domain}.");
        return 0;
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
                    $user->assignRole("{$moduleName}-manager");
                } else {
                    $user->assignRole("{$moduleName}-user");
                }
            }
        });
    }
} 