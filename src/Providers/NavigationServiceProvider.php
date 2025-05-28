<?php

namespace Egerstudios\TenantModules\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;

/**
 * NavigationServiceProvider
 * 
 * This service provider is responsible for:
 * 1. Managing navigation items for all modules
 * 2. Providing a Blade directive (@moduleNavigation) for rendering navigation
 * 3. Handling permission-based navigation visibility
 * 
 * The NavigationServiceProvider works in conjunction with:
 * - ModuleServiceProvider: Provides the module navigation data
 * - ModuleManager: Manages which modules are active
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
        // Register the @moduleNavigation directive
        Blade::directive('moduleNavigation', function () {
            return "<?php echo \$__env->make('components.navigation.module-navigation', ['navigation' => \$moduleNavigation ?? []], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>";
        });

        // Share navigation items with all views after all modules are registered
        $this->app->booted(function () {
            $flattenedNavigation = collect($this->navigationItems)->flatten(1)->toArray();
            Log::debug('Flattened navigation items:', ['items' => $flattenedNavigation]);
            View::share('moduleNavigation', $flattenedNavigation);

            Log::debug('Navigation items registered:', [
                'raw_items' => $this->navigationItems,
                'flattened_items' => $flattenedNavigation
            ]);
        });
    }

    /**
     * Register navigation items for a module.
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
    }

    /**
     * Get all registered navigation items.
     */
    public function getNavigationItems(): array
    {
        Log::debug('Getting navigation items:', [
            'raw_items' => $this->navigationItems,
            'flattened_items' => collect($this->navigationItems)->flatten(1)->toArray()
        ]);
        return $this->navigationItems;
    }

    /**
     * Render all navigation items.
     */
    public function render(): string
    {
        Log::debug('Starting navigation render', ['navigation_items' => $this->navigationItems]);
        
        $html = '';
        foreach ($this->navigationItems as $module => $items) {
            $moduleHtml = $this->renderModuleNavigation($module, $items);
            Log::debug("Rendered HTML for module {$module}", ['html' => $moduleHtml]);
            $html .= $moduleHtml;
        }
        
        Log::debug('Final rendered navigation HTML', ['html' => $html]);
        return $html;
    }

    /**
     * Render navigation items for a specific module.
     */
    protected function renderModuleNavigation(string $module, array $items): string
    {
        Log::debug("Rendering navigation for module: {$module}", [
            'module' => $module,
            'items' => $items,
            'user_permissions' => auth()->user() ? auth()->user()->getAllPermissions()->pluck('name') : []
        ]);
        
        $html = "<flux:navlist.group heading=\"{$module}\" class=\"grid\">";
        
        foreach ($items as $item) {
            $icon = $item['icon'] ?? 'circle';
            $route = $item['route'] ?? '#';
            $label = $item['label'] ?? '';
            $permission = $item['permission'] ?? null;
            
            if ($permission && !auth()->user()->can($permission)) {
                Log::debug("Skipping navigation item due to missing permission", [
                    'module' => $module,
                    'item' => $item,
                    'required_permission' => $permission
                ]);
                continue;
            }

            $html .= "<flux:navlist.item icon=\"{$icon}\" :href=\"route('{$route}')\" :current=\"request()->routeIs('{$route}')\" wire:navigate>{$label}</flux:navlist.item>";
        }
        
        $html .= "</flux:navlist.group>";
        
        Log::debug("Generated HTML for module {$module}", ['html' => $html]);
        return $html;
    }
} 