<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Egerstudios\TenantModules\Models\Module;

class ModuleDeleteCommand extends Command
{
    protected $signature = 'module:delete {name} {--force}';
    protected $description = 'Delete a module and its database record';

    public function handle()
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
        }

        // Delete module files
        if (File::exists($modulePath)) {
            File::deleteDirectory($modulePath);
            $this->info("Module files deleted successfully.");
        }

        // Delete module record and related records
        $module->tenants()->detach(); // Remove all tenant associations
        $module->delete(); // Delete the module record

        $this->info("Module {$name} has been deleted successfully!");
        return 0;
    }
} 