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
        
        // Load PHP language files with proper namespace
        if (is_dir($langPath)) {
            $locale = app()->getLocale();
            $fallbackLocale = config('app.fallback_locale');
            
            // Load current locale PHP files
            $currentLocalePath = "{$langPath}/{$locale}";
            if (is_dir($currentLocalePath)) {
                foreach (glob("{$currentLocalePath}/*.php") as $file) {
                    $namespace = basename($file, '.php');
                    $translations = require $file;
                    if (is_array($translations)) {
                        $this->app['translator']->addNamespace(
                            "{$this->moduleNameLower}::{$namespace}",
                            dirname($file)
                        );
                    }
                }
            }
            
            // Load fallback locale PHP files
            $fallbackLocalePath = "{$langPath}/{$fallbackLocale}";
            if (is_dir($fallbackLocalePath)) {
                foreach (glob("{$fallbackLocalePath}/*.php") as $file) {
                    $namespace = basename($file, '.php');
                    $translations = require $file;
                    if (is_array($translations)) {
                        $this->app['translator']->addNamespace(
                            "{$this->moduleNameLower}::{$namespace}",
                            dirname($file)
                        );
                    }
                }
            }
        }
        
        // Register module views with namespace
        $viewPath = module_path($this->moduleName, 'resources/views');
        \Log::debug("Registering views for {$this->moduleName}", [
            'path' => $viewPath,
            'namespace' => $this->moduleNameLower
        ]);
        View::addNamespace($this->moduleNameLower, $viewPath);
        
        // Register Livewire components
        if (class_exists(\Livewire\Livewire::class)) {
            $livewirePath = module_path($this->moduleName, 'Livewire');
            if (is_dir($livewirePath)) {
                \Log::debug("Registering Livewire components for {$this->moduleName}", [
                    'path' => $livewirePath
                ]);
                $this->registerLivewireComponentsRecursively($livewirePath, 'Modules\\' . $this->moduleName . '\\Livewire');
            }
        }

        // Register Volt components
        if (class_exists('Livewire\Volt\Volt')) {
            $voltPath = module_path($this->moduleName, 'resources/views/livewire');
            if (is_dir($voltPath)) {
                \Livewire\Volt\Volt::mount([
                    $voltPath => "Modules\\{$this->moduleName}\\Livewire",
                ]);
            }
        }
        
        // Auto-discover Volt components
        if (function_exists('Volt\\discover')) {
            \Volt\discover(base_path("modules/{$this->moduleName}/resources/components"), $this->moduleNameLower);
        }
        
        $this->registerNavigation();
        $this->registerCommands();
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

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Register your module's commands here
            ]);
        }
    }

    protected function registerLivewireComponentsRecursively($directory, $baseNamespace, $subPath = '')
    {
        $items = glob($directory . '/*');
        foreach ($items as $item) {
            if (is_dir($item)) {
                $subDirName = basename($item);
                $newSubPath = $subPath ? $subPath . '/' . strtolower($subDirName) : strtolower($subDirName);
                $newNamespace = $baseNamespace . '\\' . $subDirName;
                $this->registerLivewireComponentsRecursively($item, $newNamespace, $newSubPath);
            } elseif (is_file($item) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                $className = basename($item, '.php');
                $fullNamespace = $baseNamespace . '\\' . $className;
                
                // Register with kebab-case name (for Livewire 3 compatibility)
                $kebabName = str_replace(['_', ' '], '-', strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className)));
                if ($subPath) {
                    $kebabName = str_replace('/', '.', strtolower($subPath)) . '.' . $kebabName;
                }
                
                // Register with namespaced name (for module namespace compatibility)
                $namespacedName = $this->moduleNameLower . '::' . $kebabName;
                
                \Log::debug("Registering Livewire component", [
                    'module' => $this->moduleName,
                    'kebab_name' => $kebabName,
                    'namespaced_name' => $namespacedName,
                    'class' => $fullNamespace,
                    'file' => $item
                ]);
                
                // Register both formats
                \Livewire\Livewire::component($kebabName, $fullNamespace);
                \Livewire\Livewire::component($namespacedName, $fullNamespace);
            }
        }
    }
} 