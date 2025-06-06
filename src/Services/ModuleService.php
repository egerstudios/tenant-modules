<?php

namespace Egerstudios\TenantModules\Services;

use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Support\Facades\Event;

class ModuleService
{
    /**
     * Enable a module for a tenant
     */
    public function enable(Tenant $tenant, string $moduleName): bool
    {
        try {
            $domain = $tenant->domains->first()->domain;
            
            $input = new ArrayInput([
                'module' => $moduleName,
                '--domain' => $domain
            ]);
            $output = new BufferedOutput();
            
            Artisan::call('module:enable', [
                'module' => $moduleName,
                '--domain' => $domain
            ], $output);

            Log::info("Module {$moduleName} enabled for tenant {$domain}", [
                'output' => $output->fetch()
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to enable module {$moduleName} for tenant {$domain}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Disable a module for a tenant
     */
    public function disable(Tenant $tenant, string $moduleName): bool
    {
        try {
            $domain = $tenant->domains->first()->domain;
            
            $input = new ArrayInput([
                'module' => $moduleName,
                '--domain' => $domain
            ]);
            $output = new BufferedOutput();
            
            Artisan::call('module:disable', [
                'module' => $moduleName,
                '--domain' => $domain
            ], $output);

            Log::info("Module {$moduleName} disabled for tenant {$domain}", [
                'output' => $output->fetch()
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to disable module {$moduleName} for tenant {$domain}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    
} 