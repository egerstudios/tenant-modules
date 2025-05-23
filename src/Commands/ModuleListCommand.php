<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list';
    protected $description = 'List all available modules';

    public function handle()
    {
        $modulesPath = base_path(config('modules.path'));
        $modules = [];

        if (File::isDirectory($modulesPath)) {
            foreach (File::directories($modulesPath) as $modulePath) {
                $moduleName = basename($modulePath);
                $configPath = "{$modulePath}/config/module.php";

                if (File::exists($configPath)) {
                    $config = require $configPath;
                    $modules[] = [
                        'name' => $moduleName,
                        'description' => $config['description'] ?? 'No description',
                        'version' => $config['version'] ?? '1.0.0',
                        'status' => $this->getModuleStatus($moduleName),
                    ];
                }
            }
        }

        if (empty($modules)) {
            $this->info('No modules found.');
            return 0;
        }

        $this->table(
            ['Name', 'Description', 'Version', 'Status'],
            collect($modules)->map(function ($module) {
                return [
                    $module['name'],
                    $module['description'],
                    $module['version'],
                    $module['status'],
                ];
            })
        );

        return 0;
    }

    protected function getModuleStatus(string $moduleName): string
    {
        $activeTenants = \DB::table('tenant_modules')
            ->where('module_name', $moduleName)
            ->where('is_active', true)
            ->count();

        if ($activeTenants === 0) {
            return '<fg=red>Inactive</>';
        }

        return "<fg=green>Active ({$activeTenants} tenants)</>";
    }
} 