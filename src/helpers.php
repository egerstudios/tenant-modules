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
        $modulePath = base_path(config('modules.path')) . '/' . $module;
        return $path ? $modulePath . '/' . $path : $modulePath;
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