<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;
use Egerstudios\TenantModules\Models\ModuleLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

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

        // Run module cleanup in tenant context
        $tenant->run(function () use ($moduleName, $tenant) {
            $this->info("Cleaning up module {$moduleName}...");
            
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

            // Rollback module migrations
            $migrationPath = "modules/{$moduleName}/database/migrations";
            if (file_exists(database_path($migrationPath))) {
                $this->info("Rolling back migrations for module {$moduleName}...");
                Artisan::call('migrate:rollback', [
                    '--path' => $migrationPath,
                    '--force' => true
                ]);
            }

            // Log the action
            if (Schema::hasTable('module_logs')) {
                ModuleLog::create([
                    'tenant_id' => $tenant->id,
                    'module_name' => $moduleName,
                    'action' => 'disabled',
                    'occurred_at' => now()
                ]);
            }
        });

        $this->info("Module {$moduleName} has been disabled for tenant {$domain}.");
        return 0;
    }
} 