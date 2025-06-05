<?php

namespace Egerstudios\TenantModules\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Egerstudios\TenantModules\Models\Module;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Egerstudios\TenantModules\Http\Middleware\ModuleAccessMiddleware;

class ModuleRouteServiceProvider extends ServiceProvider
{
    /**
     * Define the routes for the application.
     */
    public function boot(): void
    {
        $this->routes(function () {
            // Get all modules that are enabled in their config
            $modules = modules();
            $enabledModules = array_filter(array_keys($modules), function($module) use ($modules) {
                return $modules[$module]['enabled'] ?? false;
            });

            // Check if the modules table exists before querying
            if (!Schema::hasTable('modules')) {
                return;
            }

            try {
                // Filter to only those that are activated for the current tenant
                $tenantEnabledModules = Module::whereIn('name', $enabledModules)
                    ->whereHas('tenants', function ($query) {
                        $query->where('tenant_modules.is_active', true);
                    })
                    ->pluck('name')
                    ->toArray();

                foreach ($tenantEnabledModules as $module) {
                    $this->registerModuleWebRoutes($module);
                    $this->registerModuleApiRoutes($module);
                }
            } catch (\Exception $e) {
                // If there's any database error, just return without registering routes
                return;
            }
        });
    }

    /**
     * Register web routes for a module.
     */
    protected function registerModuleWebRoutes(string $module): void
    {
        $routeFile = module_path($module, 'routes/tenant.php');
        if (file_exists($routeFile)) {
            Route::group([
                'prefix' => strtolower($module),
                'middleware' => [
                    'web',
                    'auth',
                    InitializeTenancyBySubdomain::class,
                    PreventAccessFromCentralDomains::class,
                    ModuleAccessMiddleware::class . ':' . strtolower($module)
                ],
                'namespace' => "Modules\\{$module}\\Http\\Controllers",
            ], function () use ($routeFile) {
                $this->loadRoutesFrom($routeFile);
            });
        }
    }

    /**
     * Register API routes for a module.
     */
    protected function registerModuleApiRoutes(string $module): void
    {
        $routeFile = module_path($module, 'routes/api.php');
        if (file_exists($routeFile)) {
            Route::group([
                'prefix' => 'api/' . strtolower($module),
                'middleware' => [
                    'api',
                    InitializeTenancyBySubdomain::class,
                    PreventAccessFromCentralDomains::class,
                    ModuleAccessMiddleware::class . ':' . strtolower($module)
                ],
                'namespace' => "Modules\\{$module}\\Http\\Controllers",
            ], function () use ($routeFile) {
                $this->loadRoutesFrom($routeFile);
            });
        }
    }
} 