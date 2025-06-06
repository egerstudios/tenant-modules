<?php

namespace Egerstudios\TenantModules;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\Yaml\Yaml;
use Egerstudios\TenantModules\Traits\HasTranslations;

abstract class BaseModuleServiceProvider extends ServiceProvider
{
    use HasTranslations;

    protected string $moduleName;
    protected string $moduleNameLower;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->moduleName = str_replace('ServiceProvider', '', class_basename($this));
        $this->moduleNameLower = strtolower($this->moduleName);
    }

    public function register(): void
    {
        // Register module config
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'config/module.php'), $this->moduleNameLower
        );
    }

    public function boot(): void
    {
        // Register module navigation
        \Log::debug("Booting module {$this->moduleName}");
        
        // Register module translations
        $langPath = module_path($this->moduleName, 'lang');
        \Log::debug("Loading translations for {$this->moduleName}", [
            'path' => $langPath,
            'exists' => is_dir($langPath),
            'files' => is_dir($langPath) ? scandir($langPath) : []
        ]);
        
        // Load translations with explicit namespace
        $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        
        // Debug available translations
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale');
        \Log::debug("Available translations", [
            'module' => $this->moduleName,
            'namespace' => $this->moduleNameLower,
            'current_locale' => $locale,
            'fallback_locale' => $fallbackLocale,
            'translations' => trans()->getLoader()->load($locale, $this->moduleNameLower)
        ]);
        
        $this->registerNavigation();

        // Register module views
        $this->registerViews();

        // Register module commands
        $this->registerCommands();

        // Auto-register Livewire components in this module
        if (class_exists(\Livewire\Livewire::class)) {
            $livewirePath = module_path($this->moduleName, 'Livewire');
            if (is_dir($livewirePath)) {
                foreach (glob($livewirePath . '/*.php') as $file) {
                    $class = 'Modules\\' . $this->moduleName . '\\Livewire\\' . basename($file, '.php');
                    \Livewire\Livewire::component(strtolower($this->moduleName) . '-' . strtolower(basename($file, '.php')), $class);
                }
            }
        }

        // Auto-discover Volt components
        if (function_exists('Volt\\discover')) {
            \Volt\discover(base_path("modules/{$this->moduleName}/resources/components"), $this->moduleNameLower);
        }
    }

    protected function registerNavigation(): void
    {
        \Log::debug("Registering navigation for module {$this->moduleName}");
        $navigationPath = module_path($this->moduleName, 'config/navigation.yaml');
        \Log::debug("Registering navigation for module {$this->moduleName}", [
            'path' => $navigationPath,
            'exists' => file_exists($navigationPath)
        ]);
        
        if (file_exists($navigationPath)) {
            $navigation = Yaml::parseFile($navigationPath);
            \Log::debug("Navigation config for {$this->moduleName}", ['config' => $navigation]);
            
            // Process translations in navigation items
            $this->processNavigationTranslations($navigation['items']);
            
            // Debug the processed items
            \Log::debug("Processed navigation items for {$this->moduleName}", [
                'items' => $navigation['items'],
                'current_locale' => app()->getLocale(),
                'fallback_locale' => config('app.fallback_locale')
            ]);
            
            app('navigation')->registerModuleNavigation($this->moduleName, $navigation['items']);
        }
    }

    /**
     * Process translations in navigation items recursively
     */
    protected function processNavigationTranslations(array &$items): void
    {
        foreach ($items as &$item) {
            // Translate label if it exists
            if (isset($item['label'])) {
                // Get the translation using the module's translation namespace
                $key = $item['label'];
                $translated = trans("{$this->moduleNameLower}::{$key}");
                
                \Log::debug("Processing translation", [
                    'module' => $this->moduleName,
                    'key' => $key,
                    'full_key' => "{$this->moduleNameLower}::{$key}",
                    'translated' => $translated,
                    'available_translations' => trans()->getLoader()->load(app()->getLocale(), $this->moduleNameLower)
                ]);
                
                // If translation is the same as the key, try without the namespace
                if ($translated === "{$this->moduleNameLower}::{$key}") {
                    $translated = trans($key);
                    \Log::debug("Trying translation without namespace", [
                        'key' => $key,
                        'translated' => $translated
                    ]);
                }
                
                // If still no translation found, use the original key
                if ($translated === $key || $translated === "{$this->moduleNameLower}::{$key}") {
                    \Log::warning("Translation not found for key: {$key} in module {$this->moduleName}");
                    $translated = $key;
                }
                
                $item['label'] = $translated;
            }
            
            // Process children recursively if they exist
            if (isset($item['children']) && is_array($item['children'])) {
                $this->processNavigationTranslations($item['children']);
            }
        }
    }

    protected function registerRoutes(): void
    {
        // Register web routes with default middleware
        Route::group([
            'prefix' => $this->moduleNameLower,
            'middleware' => $this->getWebMiddleware(),
            'namespace' => "Modules\\{$this->moduleName}\\Http\\Controllers",
        ], function () {
            $this->loadRoutesFrom(module_path($this->moduleName, 'routes/tenant.php'));
        });

        // Register API routes with default middleware
        Route::group([
            'prefix' => 'api/' . $this->moduleNameLower,
            'middleware' => $this->getApiMiddleware(),
            'namespace' => "Modules\\{$this->moduleName}\\Http\\Controllers",
        ], function () {
            $this->loadRoutesFrom(module_path($this->moduleName, 'routes/api.php'));
        });
    }

    /**
     * Get the web middleware for the module's routes.
     * Override this method in your module's service provider to customize middleware.
     */
    protected function getWebMiddleware(): array
    {
        return ['web', 'auth', 'tenant'];
    }

    /**
     * Get the API middleware for the module's routes.
     * Override this method in your module's service provider to customize middleware.
     */
    protected function getApiMiddleware(): array
    {
        return ['api'];
    }

    protected function registerViews(): void
    {
        View::addNamespace($this->moduleNameLower, module_path($this->moduleName, 'resources/views'));
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Register your module's commands here
            ]);
        }
    }
} 