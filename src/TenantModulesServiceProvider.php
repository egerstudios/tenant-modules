<?php

namespace Egerstudios\TenantModules;

use Illuminate\Support\ServiceProvider;

class TenantModulesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/modules.php', 'modules'
        );

        $this->app->singleton(ModuleManager::class, function ($app) {
            return new ModuleManager();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/modules.php' => config_path('modules.php'),
            ], 'modules-config');

            $this->publishes([
                __DIR__.'/Stubs' => base_path('stubs/modules'),
            ], 'modules-stubs');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            $this->commands([
                Commands\ModuleMakeCommand::class,
                Commands\ModuleBuildCommand::class,
                Commands\ModuleListCommand::class,
            ]);
        }
    }
} 