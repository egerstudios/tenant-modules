<?php

namespace Egerstudios\TenantModules\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ModuleAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $module
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $module)
    {
        // Convert module name to title case to match filesystem
        $moduleName = Str::title($module);
        
        Log::debug("ModuleAccessMiddleware checking module", [
            'module' => $module,
            'module_name' => $moduleName,
            'tenant_id' => tenant('id'),
            'tenant_name' => tenant('name')
        ]);

        // First check if module is enabled in its config
        $moduleInfo = modules($moduleName);
        Log::debug("Module config check", [
            'module' => $module,
            'module_name' => $moduleName,
            'module_info' => $moduleInfo,
            'enabled_in_config' => $moduleInfo['enabled'] ?? false
        ]);

        if (!$moduleInfo || !($moduleInfo['enabled'] ?? false)) {
            Log::debug("Module not enabled in config", [
                'module' => $module,
                'module_name' => $moduleName,
                'has_module_info' => !is_null($moduleInfo),
                'enabled_in_config' => $moduleInfo['enabled'] ?? false
            ]);
            abort(403, "Module {$module} is not enabled in configuration.");
        }

        // Then check if it's activated for the current tenant
        $tenant = tenant();
        if (!$tenant) {
            Log::debug("No tenant context found");
            abort(403, "No tenant context found.");
        }

        $isModuleActive = $tenant->modules()
            ->where('name', $moduleName)
            ->where('tenant_modules.is_active', true)
            ->exists();

        Log::debug("Module tenant activation check", [
            'module' => $module,
            'module_name' => $moduleName,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'is_active' => $isModuleActive
        ]);

        if (!$isModuleActive) {
            abort(403, "Module {$module} is not enabled for this tenant.");
        }

        // Finally check if the user has permission to access the module
        if (!$request->user() || !$request->user()->can("{$module}.view")) {
            Log::debug("User lacks permission", [
                'module' => $module,
                'module_name' => $moduleName,
                'user_id' => $request->user()?->id,
                'permission' => "{$module}.view"
            ]);
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
} 