<?php

namespace Egerstudios\TenantModules;

use Illuminate\Support\ServiceProvider;
use Egerstudios\TenantModules\Commands\ModuleMakeCommand;
use Egerstudios\TenantModules\Commands\ModuleEnableCommand;
use Egerstudios\TenantModules\Commands\ModuleDisableCommand;
use Egerstudios\TenantModules\Commands\ModuleListCommand;
use Egerstudios\TenantModules\Commands\ModuleDeleteCommand;
use Illuminate\Support\Facades\File;

class TenantModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \Log::info('TenantModulesServiceProvider: register method called');
        $this->mergeConfigFrom(
            __DIR__.'/../config/modules.php', 'modules'
        );

        // Register helper functions
        require_once __DIR__.'/helpers.php';

        // Register module service providers
        $this->registerModuleProviders();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
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
    }

    protected function registerModuleProviders(): void
    {
        \Log::info('TenantModulesServiceProvider: Scanning for module providers');
        $modulesPath = base_path(config('modules.path', 'modules'));
        if (!is_dir($modulesPath)) {
            \Log::info('TenantModulesServiceProvider: No modules directory found');
            return;
        }

        $modules = array_filter(scandir($modulesPath), function ($item) use ($modulesPath) {
            return is_dir($modulesPath . '/' . $item) && !in_array($item, ['.', '..']);
        });

        foreach ($modules as $module) {
            $configPath = $modulesPath . '/' . $module . '/config/module.php';
            if (file_exists($configPath)) {
                $config = require $configPath;
                if ($config['enabled'] ?? false) {
                    $providerClass = "Modules\\{$module}\\Providers\\{$module}ServiceProvider";
                    \Log::info("TenantModulesServiceProvider: Checking for provider $providerClass");
                    if (class_exists($providerClass)) {
                        \Log::info("TenantModulesServiceProvider: Registering $providerClass");
                        $this->app->register($providerClass);
                    } else {
                        \Log::warning("TenantModulesServiceProvider: Provider class $providerClass does not exist");
                    }
                }
            }
        }
    }
} 