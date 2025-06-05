<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Egerstudios\TenantModules\Models\Module;
use Egerstudios\TenantModules\Models\ModuleLog;
use Egerstudios\TenantModules\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ModuleDeleteCommand extends Command
{
    protected $signature = 'module:delete {name} {--force}';
    protected $description = 'Delete a module and its database record';

    public function handle(ModuleManager $moduleManager): int
    {
        $name = $this->argument('name');
        $force = $this->option('force');
        $modulePath = base_path(config('modules.path')) . '/' . $name;

        // Find the module in database
        $module = Module::where('name', $name)->first();
        if (!$module) {
            $this->error("Module {$name} not found in database!");
            return 1;
        }

        // Check if module is in use by any tenants
        if ($module->tenants()->exists()) {
            if (!$force) {
                $this->error("Module {$name} is in use by tenants. Use --force to delete anyway.");
                return 1;
            }
            $this->warn("Module {$name} is in use by tenants. Proceeding with deletion...");

            // Get all tenants using this module
            $tenants = $module->tenants;
            
            foreach ($tenants as $tenant) {
                $tenant->run(function () use ($name, $tenant) {
                    // Delete module's migrations if they exist
                    $migrationPath = "modules/{$name}/database/migrations";
                    if (File::exists(database_path($migrationPath))) {
                        $this->info("Rolling back migrations for module {$name}...");
                        Artisan::call('migrate:rollback', [
                            '--path' => $migrationPath,
                            '--force' => true
                        ]);
                    }

                    // Delete module's permissions if they exist
                    if (Schema::hasTable('permissions')) {
                        Permission::where('name', 'like', "{$name}.%")->delete();
                    }
                    
                    // Delete module's roles if they exist
                    if (Schema::hasTable('roles')) {
                        Role::where('name', 'like', "{$name}-%")->delete();
                    }
                    
                    // Delete module logs if they exist
                    if (Schema::hasTable('module_logs')) {
                        ModuleLog::where('module_name', $name)->delete();

                        // Log the deletion
                        ModuleLog::create([
                            'tenant_id' => $tenant->id,
                            'module_name' => $name,
                            'action' => 'deleted',
                            'occurred_at' => now()
                        ]);
                    }
                });
            }
        }

        // Delete module files
        if (File::exists($modulePath)) {
            File::deleteDirectory($modulePath);
            $this->info("Module files deleted successfully.");
        }

        // Delete module record and related records in central context
        DB::connection('mysql')->transaction(function () use ($module) {
            // Remove all tenant associations
            DB::table('tenant_modules')->where('module_id', $module->id)->delete();
            
            // Delete the module record
            DB::table('modules')->where('id', $module->id)->delete();
        });

        $this->info("✅ Module {$name} has been deleted successfully!");
        return 0;
    }
} 