<?php

namespace Egerstudios\TenantModules\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Egerstudios\TenantModules\Services\ModuleManager;
use Egerstudios\TenantModules\Commands\ModuleEnableCommand;
use Egerstudios\TenantModules\Commands\ModuleDisableCommand;
use Egerstudios\TenantModules\Commands\ModuleMakeCommand;
use Egerstudios\TenantModules\Commands\ModuleListCommand;
use Egerstudios\TenantModules\Commands\ModuleDeleteCommand;
use Egerstudios\TenantModules\Commands\ModuleStatusCommand;
use Egerstudios\TenantModules\Events\ModuleStateChanged;
use Illuminate\Support\Facades\Log;

/**
 * TenantModulesServiceProvider - Main service provider for the module system
 * 
 * Registers all services, commands, and providers for the tenant module system.
 */
class TenantModulesServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Register the ModuleManager as singleton
        $this->app->singleton(ModuleManager::class, function ($app) {
            return new ModuleManager();
        });

        // Register other providers
        $this->app->register(NavigationServiceProvider::class);
        $this->app->register(ModuleRouteServiceProvider::class);

        // Load helper functions
        require_once __DIR__ . '/../helpers.php';

        // Register commands
        $this->commands([
            ModuleEnableCommand::class,
            ModuleDisableCommand::class,
            ModuleStatusCommand::class,
            ModuleListCommand::class,
            ModuleMakeCommand::class,
            ModuleDeleteCommand::class,
        ]);
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'tenant-modules');
        
        // Register Livewire components
        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::component('tenant-modules::module-navigation', \Egerstudios\TenantModules\Livewire\ModuleNavigation::class);
        }

        // Publish migrations to central database
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'tenant-modules-migrations');

        // Publish configuration files
        $this->publishes([
            __DIR__ . '/../config/tenant-modules.php' => config_path('tenant-modules.php'),
        ], 'tenant-modules-config');

        // Register event listeners for cross-cutting concerns
        $this->registerEventListeners();

        // Defer module registration until after migrations
        if (!$this->app->runningInConsole() || $this->app->runningUnitTests()) {
            $this->registerModuleProviders();
        }
    }

    /**
     * Register event listeners for logging and other cross-cutting concerns
     */
    protected function registerEventListeners(): void
    {
        // Log all module state changes
        Event::listen(ModuleStateChanged::class, function (ModuleStateChanged $event) {
            logger()->info('Module state changed', [
                'tenant_id' => $event->tenant->id,
                'module' => $event->moduleName,
                'action' => $event->getAction(),
                'timestamp' => $event->timestamp->toISOString(),
            ]);
        });

        // Optional: Clear relevant caches when modules change
        Event::listen(ModuleStateChanged::class, function (ModuleStateChanged $event) {
            // Clear module-specific caches
            cache()->forget("tenant.{$event->tenant->id}.modules");
            cache()->forget("tenant.{$event->tenant->id}.permissions");
        });
    }

    /**
     * Register module service providers
     */
    protected function registerModuleProviders(): void
    {
        // Get all modules that are enabled in their config
        $modules = modules();
        $enabledModules = array_filter(array_keys($modules), function($module) use ($modules) {
            return $modules[$module]['enabled'] ?? false;
        });

        // Check for modules in filesystem that aren't in database
        $this->registerFilesystemModules($enabledModules);

        // Filter to only those that are activated for the current tenant
        $tenantEnabledModules = \Egerstudios\TenantModules\Models\Module::whereIn('name', $enabledModules)
            ->whereHas('tenants', function ($query) {
                $query->where('tenant_modules.is_active', true);
            })
            ->pluck('name')
            ->toArray();

        Log::debug('Registering providers for enabled modules', [
            'enabled_modules' => $enabledModules,
            'tenant_enabled_modules' => $tenantEnabledModules
        ]);

        foreach ($tenantEnabledModules as $module) {
            $providerClass = "Modules\\{$module}\\Providers\\{$module}ServiceProvider";
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    /**
     * Register modules that exist in filesystem but not in database
     */
    protected function registerFilesystemModules(array $enabledModules): void
    {
        $modulePath = base_path('modules');
        
        if (!is_dir($modulePath)) {
            return;
        }

        $directories = array_filter(glob($modulePath . '/*'), 'is_dir');
        
        foreach ($directories as $directory) {
            $moduleName = basename($directory);
            
            // Skip if module is not enabled in config
            if (!in_array($moduleName, $enabledModules)) {
                continue;
            }

            // Check if module exists in database
            $module = \Egerstudios\TenantModules\Models\Module::where('name', $moduleName)->first();
            
            if (!$module) {
                // Create new module in database
                $module = \Egerstudios\TenantModules\Models\Module::create([
                    'name' => $moduleName,
                    'description' => $this->getModuleDescription($moduleName),
                ]);

                Log::info('Registered new module from filesystem', [
                    'module' => $moduleName,
                    'description' => $module->description
                ]);
            }
        }
    }

    /**
     * Get module description from config file
     */
    protected function getModuleDescription(string $moduleName): string
    {
        $configPath = base_path("modules/{$moduleName}/config/config.php");
        
        if (file_exists($configPath)) {
            $config = require $configPath;
            return $config['description'] ?? "Module {$moduleName}";
        }
        
        return "Module {$moduleName}";
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            ModuleManager::class,
            'navigation',
        ];
    }
}