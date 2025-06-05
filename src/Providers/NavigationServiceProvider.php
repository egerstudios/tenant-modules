<?php

namespace Egerstudios\TenantModules\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Yaml\Yaml;
use Egerstudios\TenantModules\Events\ModuleStateChanged;
use Egerstudios\TenantModules\Services\ModuleManager;

/**
 * NavigationServiceProvider
 * 
 * This service provider is responsible for:
 * 1. Managing navigation items for all modules
 * 2. Providing a Blade directive (@moduleNavigation) for rendering navigation
 * 3. Handling permission-based navigation visibility
 */
class NavigationServiceProvider extends ServiceProvider
{
    /**
     * Storage for all registered navigation items.
     * Structure: ['module_name' => [navigation_items]]
     */
    protected $navigationItems = [];

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('navigation', function ($app) {
            return $this;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Log::debug('NavigationServiceProvider booting');
        
        // Register the @moduleNavigation directive
        Blade::directive('moduleNavigation', function () {
            return "<?php echo \$__env->make('components.navigation.module-navigation', ['navigation' => \$moduleNavigation ?? []], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>";
        });

        // Listen for module state changes
        Event::listen(ModuleStateChanged::class, [$this, 'handleModuleStateChange']);

        // Listen for tenant switching events
        Event::listen('Stancl\Tenancy\Events\TenancyBootstrapped', function ($event) {
            Log::debug('Tenancy bootstrapped, refreshing navigation', [
                'tenant_id' => $event->tenancy->tenant->id
            ]);
            $this->refreshNavigationForTenant($event->tenancy->tenant);
        });

        Event::listen('Stancl\Tenancy\Events\TenancyEnded', function () {
            Log::debug('Tenancy ended, clearing navigation');
            $this->navigationItems = [];
            View::share('moduleNavigation', []);
        });

        // Share navigation items with all views after all modules are registered
        $this->app->booted(function () {
            if (tenant()) {
                $this->refreshNavigationForTenant(tenant());
            }
        });
    }

    /**
     * Handle module state changes
     */
    public function handleModuleStateChange(ModuleStateChanged $event): void
    {
        Log::debug("Handling module state change", [
            'module' => $event->moduleName,
            'action' => $event->getAction()
        ]);

        if ($event->isEnabled()) {
            // When module is enabled, we need to re-register its navigation items
            $navigationPath = module_path($event->moduleName, 'config/navigation.yaml');
            if (file_exists($navigationPath)) {
                $navigation = Yaml::parseFile($navigationPath);
                $this->registerModuleNavigation($event->moduleName, $navigation['items']);
            }
        } else {
            // When module is disabled, remove its navigation items
            $this->removeModuleNavigation($event->moduleName);
        }
        
        $this->refreshSharedNavigation();
    }

    /**
     * Remove navigation for a specific module
     */
    protected function removeModuleNavigation(string $moduleName): void
    {
        unset($this->navigationItems[$moduleName]);
        Log::debug("Removed navigation for module {$moduleName}");
    }

    /**
     * Register navigation items for a module
     */
    public function registerModuleNavigation(string $module, array $items): void
    {
        Log::debug("Registering navigation for module: {$module}", [
            'module' => $module,
            'items' => $items,
            'current_navigation_items' => $this->navigationItems
        ]);
        
        // Validate navigation items structure
        foreach ($items as $item) {
            if (!isset($item['label'])) {
                Log::warning("Navigation item missing 'label' for module {$module}", ['item' => $item]);
            }
            if (!isset($item['route'])) {
                Log::warning("Navigation item missing 'route' for module {$module}", ['item' => $item]);
            }
        }
        
        $this->navigationItems[$module] = $items;
        
        Log::debug("Navigation items after registration:", [
            'module' => $module,
            'updated_navigation_items' => $this->navigationItems
        ]);

        $this->refreshSharedNavigation();
    }

    /**
     * Get all registered navigation items
     */
    public function getNavigationItems(): array
    {
        return $this->navigationItems;
    }

    /**
     * Get flattened navigation items
     */
    public function getFlattenedNavigationItems(): array
    {
        $flattened = collect($this->navigationItems)->flatten(1)->values()->all();
        
        Log::debug('Getting flattened navigation items', [
            'raw_items' => $this->navigationItems,
            'flattened_count' => count($flattened)
        ]);
        
        return $flattened;
    }

    /**
     * Refresh shared navigation data for all views
     */
    protected function refreshSharedNavigation(): void
    {
        $flattenedNavigation = collect($this->navigationItems)->flatten(1)->toArray();
        
        Log::debug('Refreshing shared navigation', [
            'items_count' => count($flattenedNavigation)
        ]);
        
        // Clear the navigation cache for the current tenant
        if (tenant()) {
            cache()->forget("tenant." . tenant()->id . ".navigation");
        }
        
        View::share('moduleNavigation', $flattenedNavigation);
    }

    /**
     * Check if a navigation item should be visible to current user
     */
    public function canViewNavigationItem(array $item): bool
    {
        if (!isset($item['permission'])) {
            return true;
        }

        return auth()->user()?->can($item['permission']) ?? false;
    }

    /**
     * Refresh navigation items for a specific tenant
     */
    protected function refreshNavigationForTenant($tenant): void
    {
        // Get all modules that are enabled for the tenant
        $enabledModules = $tenant->modules()
            ->wherePivot('is_active', true)
            ->pluck('name')
            ->toArray();
        
        Log::debug('Found enabled modules for tenant', [
            'tenant_id' => $tenant->id,
            'enabled_modules' => $enabledModules,
            'current_navigation_items' => $this->navigationItems
        ]);
        
        // Filter navigation items to only include enabled modules
        $filteredNavigation = [];
        foreach ($this->navigationItems as $moduleName => $items) {
            if (in_array($moduleName, $enabledModules)) {
                $filteredNavigation[$moduleName] = $items;
            }
        }
        
        $this->navigationItems = $filteredNavigation;
        
        Log::debug('Filtered navigation items', [
            'enabled_modules' => $enabledModules,
            'filtered_items' => $this->navigationItems
        ]);

        $flattenedNavigation = collect($this->navigationItems)->flatten(1)->toArray();
        View::share('moduleNavigation', $flattenedNavigation);
    }
}