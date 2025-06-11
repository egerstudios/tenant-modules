<?php

namespace Egerstudios\TenantModules;

use Illuminate\Support\ServiceProvider;
use Egerstudios\TenantModules\Commands\ModuleMakeCommand;
use Egerstudios\TenantModules\Commands\ModuleEnableCommand;
use Egerstudios\TenantModules\Commands\ModuleDisableCommand;
use Egerstudios\TenantModules\Commands\ModuleDeleteCommand;
use Egerstudios\TenantModules\Commands\ModuleStatusCommand;
use Egerstudios\TenantModules\Commands\ModuleListCommand;
use Egerstudios\TenantModules\Commands\ModuleBuildCommand;
use Egerstudios\TenantModules\Commands\ModuleMigrateCommand;

class TenantModulesServiceProvider extends ServiceProvider
{
    protected $commands = [
        ModuleMakeCommand::class,
        ModuleEnableCommand::class,
        ModuleDisableCommand::class,
        ModuleDeleteCommand::class,
        ModuleStatusCommand::class,
        ModuleListCommand::class,
        ModuleBuildCommand::class,
        ModuleMigrateCommand::class,
    ];

    public function register()
    {
        $this->commands($this->commands);
    }

    public function boot()
    {
        // Your boot logic here
    }
} 