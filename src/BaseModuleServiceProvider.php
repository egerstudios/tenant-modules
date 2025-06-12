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
        
        /**
         * Load module translations with proper namespacing
         * 
         * This implementation supports both JSON and PHP language files:
         * 1. JSON files: Stored in lang/{locale}.json
         * 2. PHP files: Stored in lang/{locale}/{file}.php
         * 
         * Example directory structure:
         * modules/YourModule/
         * ├── lang/
         * │   ├── en/
         * │   │   ├── general.php
         * │   │   └── validation.php
         * │   ├── nb-no/
         * │   │   ├── general.php
         * │   │   └── validation.php
         * │   ├── en.json
         * │   └── nb-no.json
         * 
         * Usage in code:
         * - For PHP files: __('yourmodule::general.key')
         * - For JSON files: __('yourmodule::key')
         * 
         * The namespace is automatically set to the lowercase module name
         * (e.g., 'yourmodule' for a module named 'YourModule')
         */
        
        // Load translations with explicit namespace
        $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        
        // Load PHP language files with proper namespace
        if (is_dir($langPath)) {
            $locale = app()->getLocale();
            $fallbackLocale = config('app.fallback_locale');
            
            \Log::debug("Loading PHP translations", [
                'module' => $this->moduleName,
                'locale' => $locale,
                'fallback_locale' => $fallbackLocale
            ]);
            
            // Load current locale PHP files
            $currentLocalePath = "{$langPath}/{$locale}";
            if (is_dir($currentLocalePath)) {
                \Log::debug("Loading current locale PHP files", [
                    'path' => $currentLocalePath,
                    'files' => glob("{$currentLocalePath}/*.php")
                ]);
                
                foreach (glob("{$currentLocalePath}/*.php") as $file) {
                    $namespace = basename($file, '.php');
                    $translations = require $file;
                    \Log::debug("Loaded PHP translations", [
                        'file' => $file,
                        'namespace' => $namespace,
                        'has_translations' => is_array($translations),
                        'translation_keys' => is_array($translations) ? array_keys($translations) : []
                    ]);
                    
                    if (is_array($translations)) {
                        // Register the namespace for this specific file
                        $this->app['translator']->addNamespace(
                            "{$this->moduleNameLower}::{$namespace}",
                            dirname($file)
                        );
                        \Log::debug("Added namespace for translations", [
                            'namespace' => "{$this->moduleNameLower}::{$namespace}",
                            'path' => dirname($file)
                        ]);
                    }
                }
            }
            
            // Load fallback locale PHP files
            $fallbackLocalePath = "{$langPath}/{$fallbackLocale}";
            if (is_dir($fallbackLocalePath)) {
                \Log::debug("Loading fallback locale PHP files", [
                    'path' => $fallbackLocalePath,
                    'files' => glob("{$fallbackLocalePath}/*.php")
                ]);
                
                foreach (glob("{$fallbackLocalePath}/*.php") as $file) {
                    $namespace = basename($file, '.php');
                    $translations = require $file;
                    \Log::debug("Loaded fallback PHP translations", [
                        'file' => $file,
                        'namespace' => $namespace,
                        'has_translations' => is_array($translations),
                        'translation_keys' => is_array($translations) ? array_keys($translations) : []
                    ]);
                    
                    if (is_array($translations)) {
                        // Register the namespace for this specific file
                        $this->app['translator']->addNamespace(
                            "{$this->moduleNameLower}::{$namespace}",
                            dirname($file)
                        );
                        \Log::debug("Added namespace for fallback translations", [
                            'namespace' => "{$this->moduleNameLower}::{$namespace}",
                            'path' => dirname($file)
                        ]);
                    }
                }
            }
        }
        
        // Register Volt components
        if (class_exists('Livewire\Volt\Volt')) {
            $voltPath = module_path($this->moduleName, 'resources/views/livewire');
            if (is_dir($voltPath)) {
                \Livewire\Volt\Volt::mount([
                    $voltPath => "Modules\\{$this->moduleName}\\Livewire",
                ]);
                \Log::debug("Registered Volt components", [
                    'module' => $this->moduleName,
                    'path' => $voltPath
                ]);
            }
        }
        
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
                
                // Try to get translation from JSON file directly
                $locale = app()->getLocale();
                $fallbackLocale = config('app.fallback_locale');
                $langPath = module_path($this->moduleName, 'lang');
                
                // Try current locale first
                $currentLocalePath = "{$langPath}/{$locale}.json";
                $translated = null;
                
                if (file_exists($currentLocalePath)) {
                    $translations = json_decode(file_get_contents($currentLocalePath), true);
                    $translated = data_get($translations, $key);
                }
                
                // If not found, try fallback locale
                if (!$translated && file_exists("{$langPath}/{$fallbackLocale}.json")) {
                    $translations = json_decode(file_get_contents("{$langPath}/{$fallbackLocale}.json"), true);
                    $translated = data_get($translations, $key);
                }
                
                \Log::debug("Processing translation", [
                    'module' => $this->moduleName,
                    'key' => $key,
                    'translated' => $translated,
                    'current_locale' => $locale,
                    'fallback_locale' => $fallbackLocale
                ]);
                
                // If translation found, use it
                if ($translated) {
                    $item['label'] = $translated;
                } else {
                    \Log::warning("Translation not found for key: {$key} in module {$this->moduleName}");
                    $item['label'] = $key;
                }
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
        $namespace = $this->moduleNameLower;
        $path = module_path($this->moduleName, 'resources/views');
        \Log::debug("Registering view namespace", ['namespace' => $namespace, 'path' => $path]);
        View::addNamespace($namespace, $path);
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