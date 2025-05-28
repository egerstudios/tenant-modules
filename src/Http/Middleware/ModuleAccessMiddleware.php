<?php

namespace Egerstudios\TenantModules\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

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
        // First check if the module is enabled for the tenant
        if (!module_enabled($module)) {
            abort(403, "Module {$module} is not enabled for this tenant.");
        }

        // Then check if the user has permission to access the module
        if (!$request->user() || !$request->user()->can("{$module}.access")) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
} 