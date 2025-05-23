<?php

namespace Egerstudios\TenantModules;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleManager
{
    protected array $modules = [];
    protected string $modulesPath;
    protected string $cachePath;

    public function __construct()
    {
        $this->modulesPath = base_path('modules');
        $this->cachePath = storage_path('framework/cache/modules.php');
        $this->loadModules();
    }

    public function loadModules(): void
    {
        if (!File::exists($this->modulesPath)) {
            File::makeDirectory($this->modulesPath, 0755, true);
            return;
        }

        $directories = File::directories($this->modulesPath);
        
        foreach ($directories as $directory) {
            $moduleName = basename($directory);
            $moduleConfig = $this->getModuleConfig($directory);
            
            if ($moduleConfig) {
                $this->modules[$moduleName] = $moduleConfig;
            }
        }
    }

    public function getModuleConfig(string $path): ?array
    {
        $configPath = $path . '/module.yaml';
        
        if (!File::exists($configPath)) {
            return null;
        }

        return yaml_parse_file($configPath);
    }

    public function getModules(): array
    {
        return $this->modules;
    }

    public function getModule(string $name): ?array
    {
        return $this->modules[$name] ?? null;
    }

    public function isEnabled(string $name): bool
    {
        return isset($this->modules[$name]) && ($this->modules[$name]['enabled'] ?? false);
    }

    public function enable(string $name): bool
    {
        if (!isset($this->modules[$name])) {
            return false;
        }

        $this->modules[$name]['enabled'] = true;
        $this->saveModules();
        return true;
    }

    public function disable(string $name): bool
    {
        if (!isset($this->modules[$name])) {
            return false;
        }

        $this->modules[$name]['enabled'] = false;
        $this->saveModules();
        return true;
    }

    protected function saveModules(): void
    {
        File::put($this->cachePath, '<?php return ' . var_export($this->modules, true) . ';');
    }
} 