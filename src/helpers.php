<?php

if (!function_exists('module_path')) {
    /**
     * Get the path to a module.
     *
     * @param string $module
     * @param string $path
     * @return string
     */
    function module_path(string $module, string $path = ''): string
    {
        return base_path(config('modules.path') . '/' . $module . ($path ? '/' . $path : ''));
    }
}

if (!function_exists('modules')) {
    /**
     * Get information about available modules.
     *
     * @param string|null $key Get specific information about a module
     * @return array|mixed
     */
    function modules(?string $key = null)
    {
        $modules = [];
        $modulesPath = base_path(config('modules.path'));

        if (!is_dir($modulesPath)) {
            return $key ? null : $modules;
        }

        foreach (glob($modulesPath . '/*', GLOB_ONLYDIR) as $modulePath) {
            $module = basename($modulePath);
            $configPath = $modulePath . '/config/module.php';

            if (file_exists($configPath)) {
                $config = require $configPath;
                $modules[$module] = array_merge([
                    'name' => $module,
                    'enabled' => $config['enabled'] ?? false,
                    'path' => $modulePath,
                ], $config);
            }
        }

        if ($key) {
            return $modules[$key] ?? null;
        }

        return $modules;
    }
}

if (!function_exists('module_enabled')) {
    /**
     * Check if a module is enabled.
     * First checks if the module is enabled in its config,
     * then checks if it's activated for the current tenant.
     */
    function module_enabled(string $module): bool
    {
        // First check if module is enabled in its config
        $moduleInfo = modules($module);
        if (!$moduleInfo || !($moduleInfo['enabled'] ?? false)) {
            return false;
        }

        // Then check if it's activated for the current tenant
        return \Egerstudios\TenantModules\Models\Module::where('name', $module)
            ->whereHas('tenants', function ($query) {
                $query->where('tenant_modules.is_active', true);
            })
            ->exists();
    }
}

if (!function_exists('module_disabled')) {
    /**
     * Check if a module is disabled.
     */
    function module_disabled(string $module): bool
    {
        return !module_enabled($module);
    }
}

if (!function_exists('module_namespace')) {
    /**
     * Get the namespace for a module.
     *
     * @param string $module
     * @param string $path
     * @return string
     */
    function module_namespace(string $module, string $path = ''): string
    {
        $namespace = config('modules.namespace') . '\\' . $module;
        return $path ? $namespace . '\\' . $path : $namespace;
    }
}

if (!function_exists('module_provider')) {
    /**
     * Get the service provider class for a module.
     *
     * @param string $module
     * @return string
     */
    function module_provider(string $module): string
    {
        return module_namespace($module, config('modules.provider'));
    }
} 