<?php

namespace Egerstudios\TenantModules;

use Illuminate\Support\ServiceProvider;
use Egerstudios\TenantModules\Commands\ModuleMakeCommand;
use Egerstudios\TenantModules\Commands\ModuleEnableCommand;
use Egerstudios\TenantModules\Commands\ModuleDisableCommand;
use Egerstudios\TenantModules\Commands\ModuleListCommand;
use Egerstudios\TenantModules\Commands\ModuleDeleteCommand;
use Egerstudios\TenantModules\Providers\NavigationServiceProvider;
use Egerstudios\TenantModules\Http\Middleware\ModuleAccessMiddleware;
use Illuminate\Support\Facades\File;

class TenantModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register config
        $this->mergeConfigFrom(
            __DIR__.'/../config/modules.php', 'modules'
        );

        // Register helper functions
        require_once __DIR__.'/helpers.php';

        // Register module route service provider
        $this->app->register(Providers\ModuleRouteServiceProvider::class);

        // Register navigation service provider
        $this->app->register(NavigationServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // Register the module access middleware
        $this->app['router']->aliasMiddleware('module', ModuleAccessMiddleware::class);
        
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleMakeCommand::class,
                ModuleEnableCommand::class,
                ModuleDisableCommand::class,
                ModuleListCommand::class,
                ModuleDeleteCommand::class,
            ]);

            // Publish config file
            $this->publishes([
                __DIR__.'/../config/modules.php' => config_path('modules.php'),
            ], 'config');
        }

        // Register module service providers
        $this->registerModuleProviders();
    }

    protected function registerModuleProviders(): void
    {
        // Get all modules that are enabled in their config
        $modules = modules();
        $enabledModules = array_filter(array_keys($modules), function($module) use ($modules) {
            return $modules[$module]['enabled'] ?? false;
        });

        // Filter to only those that are activated for the current tenant
        $tenantEnabledModules = \Egerstudios\TenantModules\Models\Module::whereIn('name', $enabledModules)
            ->whereHas('tenants', function ($query) {
                $query->where('tenant_modules.is_active', true);
            })
            ->pluck('name')
            ->toArray();

        foreach ($tenantEnabledModules as $module) {
            $providerClass = "Modules\\{$module}\\Providers\\{$module}ServiceProvider";
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }
} 