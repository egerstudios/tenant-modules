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

        \Log::debug("Scanning for modules", [
            'modules_path' => $modulesPath,
            'exists' => is_dir($modulesPath)
        ]);

        if (!is_dir($modulesPath)) {
            return $key ? null : $modules;
        }

        foreach (glob($modulesPath . '/*', GLOB_ONLYDIR) as $modulePath) {
            $module = basename($modulePath);
            $configPath = $modulePath . '/config/module.php';

            \Log::debug("Checking module config", [
                'module' => $module,
                'config_path' => $configPath,
                'exists' => file_exists($configPath)
            ]);

            if (file_exists($configPath)) {
                $config = require $configPath;
                $modules[$module] = array_merge([
                    'name' => $module,
                    'enabled' => $config['enabled'] ?? false,
                    'path' => $modulePath,
                ], $config);

                \Log::debug("Module config loaded", [
                    'module' => $module,
                    'config' => $modules[$module]
                ]);
            } else {
                // If no config file exists, check if module exists in database
                $dbModule = \Egerstudios\TenantModules\Models\Module::where('name', $module)->first();
                if ($dbModule) {
                    $modules[$module] = [
                        'name' => $module,
                        'enabled' => true, // If it exists in DB, consider it enabled
                        'path' => $modulePath,
                        'description' => $dbModule->description,
                        'version' => $dbModule->version
                    ];
                    \Log::debug("Module found in database", [
                        'module' => $module,
                        'config' => $modules[$module]
                    ]);
                }
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
        \Log::debug("Checking if module {$module} is enabled", [
            'module' => $module,
            'tenant_id' => tenant('id'),
            'tenant_name' => tenant('name')
        ]);

        // First check if module is enabled in its config
        $moduleInfo = modules($module);
        \Log::debug("Module info from config", [
            'module' => $module,
            'module_info' => $moduleInfo,
            'enabled_in_config' => $moduleInfo['enabled'] ?? false
        ]);

        if (!$moduleInfo || !($moduleInfo['enabled'] ?? false)) {
            \Log::debug("Module not enabled in config", [
                'module' => $module,
                'has_module_info' => !is_null($moduleInfo),
                'enabled_in_config' => $moduleInfo['enabled'] ?? false
            ]);
            return false;
        }

        // Get current tenant ID
        $tenantId = tenant('id');
        \Log::debug("Current tenant context", [
            'module' => $module,
            'tenant_id' => $tenantId,
            'tenant_name' => tenant('name')
        ]);

        if (!$tenantId) {
            \Log::debug("No tenant context found", [
                'module' => $module
            ]);
            return false;
        }

        // Then check if it's activated for the current tenant
        $isEnabled = \Egerstudios\TenantModules\Models\Module::where('name', $module)
            ->whereHas('tenants', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->where('tenant_modules.is_active', true);
            })
            ->exists();

        \Log::debug("Module tenant activation check", [
            'module' => $module,
            'tenant_id' => $tenantId,
            'is_enabled_for_tenant' => $isEnabled
        ]);

        return $isEnabled;
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