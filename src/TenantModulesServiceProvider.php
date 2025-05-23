<?php

namespace Egerstudios\TenantModules;

use Illuminate\Support\ServiceProvider;
use Egerstudios\TenantModules\Commands\ModuleMakeCommand;
use Egerstudios\TenantModules\Commands\ModuleBuildCommand;
use Egerstudios\TenantModules\Commands\ModuleListCommand;
use Egerstudios\TenantModules\Commands\ModuleDeleteCommand;

class TenantModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleManager::class, function ($app) {
            return new ModuleManager();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ModuleMakeCommand::class,
                ModuleBuildCommand::class,
                ModuleListCommand::class,
                ModuleDeleteCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/modules.php' => config_path('modules.php'),
            ], 'config');
        }
    }
} 