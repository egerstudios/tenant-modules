<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Tenancy;

class ModuleDeleteCommand extends Command
{
    protected $signature = 'module:delete {module : The name of the module to delete}';
    protected $description = 'Deactivate a module for all tenants and delete its files';

    public function handle()
    {
        $moduleName = $this->argument('module');
        $modulesPath = base_path(config('modules.path'));
        $modulePath = $modulesPath . '/' . $moduleName;

        if (!File::exists($modulePath)) {
            $this->warn("Module {$moduleName} does not exist.");
            return 1;
        }

        
        File::deleteDirectory($modulePath);
        $this->info("Module {$moduleName} deleted from disk.");
        return 0;
    }
} 