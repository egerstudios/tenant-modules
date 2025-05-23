<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Egerstudios\TenantModules\Models\Module;

class ModuleMakeCommand extends Command
{
    protected $signature = 'module:make {name : The name of the module}';
    protected $description = 'Create a new module';
    protected $layout = '';

    public function handle()
    {
        $name = $this->argument('name');
        $modulePath = base_path(config('modules.path')) . '/' . $name;
        $stubsPath = __DIR__ . '/../Stubs';

        if (File::exists($modulePath)) {
            $this->error("Module {$name} already exists!");
            return 1;
        }

        // Ask for the layout to use for the example blade view
        $this->layout = $this->choice('Which layout should the example blade view use?', ['tenant', 'admin', 'app'], 'tenant');

        // Create module directory structure
        $this->createDirectoryStructure($modulePath);

        // Create module files from stubs
        $this->createModuleFiles($name, $modulePath, $stubsPath);

        // Create module record in database
        Module::create([
            'name' => $name,
            'description' => "Module {$name}",
            'version' => '1.0.0',
            'is_core' => false,
            'settings_schema' => null
        ]);

        $this->info("Module {$name} created successfully!");
        return 0;
    }

    protected function createDirectoryStructure(string $modulePath): void
    {
        $directories = [
            'Http/Controllers',
            'Models',
            'Services',
            'Events',
            'Listeners',
            'Providers',
            'Http/Middleware',
            'Http/Requests',
            'Http/Resources',
            'Exceptions',
            'Console/Commands',
            'Database/Seeders',
            'Tests/Feature',
            'Tests/Unit',
            'config',
            'database/migrations',
            'database/seeders',
            'resources/views',
            'resources/css',
            'resources/js',
            'resources/components',
            'routes',
            'tests/Feature',
            'tests/Unit',
            'lang',
        ];

        foreach ($directories as $directory) {
            $dirPath = $modulePath . '/' . $directory;
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
            }
        }
    }

    protected function createModuleFiles(string $name, string $modulePath, string $stubsPath): void
    {
        $replacements = [
            '{{ module }}' => $name,
            '{{ module_snake }}' => \Illuminate\Support\Str::snake($name),
            '{{ description }}' => "The {$name} module",
            '{{ nameLower }}' => strtolower($name),
            '{{ $name }}' => $name,
            '{{ $nameLower }}' => strtolower($name),
            '{{ $module }}' => $name,
            '{{ $module_snake }}' => \Illuminate\Support\Str::snake($name),
            '{{ layout }}' => $this->layout,
        ];

        // Create module.php
        $this->createFileFromStub(
            "$stubsPath/config/module.php.stub",
            "$modulePath/config/module.php",
            $replacements
        );

        // Create navigation.yaml
        $this->createFileFromStub(
            "$stubsPath/config/navigation.yaml.stub",
            "$modulePath/config/navigation.yaml",
            $replacements
        );

        // Create permissions.yaml
        $this->createFileFromStub(
            "$stubsPath/config/permissions.yaml.stub",
            "$modulePath/config/permissions.yaml",
            $replacements
        );

        // Create database seeder with correct namespace
        $this->createFileFromStub(
            "$stubsPath/Database/Seeders/DatabaseSeeder.php.stub",
            "$modulePath/Database/Seeders/{$name}DatabaseSeeder.php",
            $replacements
        );

        // Create default view
        $this->createFileFromStub(
            "$stubsPath/resources/views/welcome.blade.php.stub",
            "$modulePath/resources/views/welcome.blade.php",
            $replacements
        );

        $this->createFileFromStub(
            "$stubsPath/resources/views/moduleinfo.blade.php.stub",
            "$modulePath/resources/views/moduleinfo.blade.php",
            $replacements
        );

        // Create default CSS
        $this->createFileFromStub(
            "$stubsPath/resources/css/app.css.stub",
            "$modulePath/resources/css/app.css",
            $replacements
        );

        // Create default JS
        $this->createFileFromStub(
            "$stubsPath/resources/js/app.js.stub",
            "$modulePath/resources/js/app.js",
            $replacements
        );

        // Create example model
        $this->createFileFromStub(
            "$stubsPath/Models/ExampleModel.php.stub",
            "$modulePath/Models/ExampleModel.php",
            $replacements
        );

        // Create example service
        $this->createFileFromStub(
            "$stubsPath/Services/ExampleService.php.stub",
            "$modulePath/Services/ExampleService.php",
            $replacements
        );

        // Create example event
        $this->createFileFromStub(
            "$stubsPath/Events/ExampleEvent.php.stub",
            "$modulePath/Events/ExampleEvent.php",
            $replacements
        );

        // Create example controller
        $this->createFileFromStub(
            "$stubsPath/Http/Controllers/ExampleController.php.stub",
            "$modulePath/Http/Controllers/ExampleController.php",
            $replacements
        );

        // Create service provider
        $this->createFileFromStub(
            "$stubsPath/Providers/ModuleServiceProvider.php.stub",
            "$modulePath/Providers/{$name}ServiceProvider.php",
            $replacements
        );

        // Create route service provider
        $this->createFileFromStub(
            "$stubsPath/Providers/RouteServiceProvider.php.stub",
            "$modulePath/Providers/RouteServiceProvider.php",
            $replacements
        );

        // Create routes
        $this->createFileFromStub(
            "$stubsPath/routes/tenant.php.stub",
            "$modulePath/routes/tenant.php",
            $replacements
        );

        $this->createFileFromStub(
            "$stubsPath/routes/api.php.stub",
            "$modulePath/routes/api.php",
            $replacements
        );

        // Create example Livewire/Volt colocated component
        $this->createExampleLivewire($name, $modulePath);

        // Create tests
        $this->createFileFromStub(
            "$stubsPath/Tests/Feature/ExampleTest.php.stub",
            "$modulePath/Tests/Feature/ExampleTest.php",
            $replacements
        );

        $this->createFileFromStub(
            "$stubsPath/Tests/Unit/ExampleUnitTest.php.stub",
            "$modulePath/Tests/Unit/ExampleUnitTest.php",
            $replacements
        );

        // Create language files
        $this->createFileFromStub(
            "$stubsPath/lang/en.json.stub",
            "$modulePath/lang/en.json",
            $replacements
        );

        // Create console command
        $this->createFileFromStub(
            "$stubsPath/Console/Commands/ExampleCommand.php.stub",
            "$modulePath/Console/Commands/ExampleCommand.php",
            $replacements
        );

        // Create middleware
        $this->createFileFromStub(
            "$stubsPath/Http/Middleware/ExampleMiddleware.php.stub",
            "$modulePath/Http/Middleware/ExampleMiddleware.php",
            $replacements
        );

        // Create form request
        $this->createFileFromStub(
            "$stubsPath/Http/Requests/ExampleRequest.php.stub",
            "$modulePath/Http/Requests/ExampleRequest.php",
            $replacements
        );

        // Create composer.json
        $this->createFileFromStub(
            "$stubsPath/composer.json.stub",
            "$modulePath/composer.json",
            $replacements
        );
    }

    protected function createFileFromStub(string $stubPath, string $targetPath, array $replacements): void
    {
        $content = $this->getStubContent($stubPath, $replacements);
        File::put($targetPath, $content);
    }

    protected function getStubContent(string $stubPath, array $replacements): string
    {
        $content = File::get($stubPath);
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function createModuleConfig($name, $modulePath)
    {
        $config = [
            'name' => $name,
            'description' => $name . ' module',
            'version' => '1.0.0',
            'requires' => [
                'core' => '>=1.0.0',
            ],
            'assets' => [
                'styles' => [
                    'public/modules/' . $name . '/css/app.css',
                ],
                'scripts' => [
                    'public/modules/' . $name . '/js/app.js',
                ],
            ],
            'navigation' => [
                [
                    'name' => $name,
                    'icon' => 'cube',
                    'route' => $name . '.index',
                    'permission' => Str::snake($name) . '.view',
                ],
            ],
            'permissions' => [
                Str::snake($name) . '.view' => 'View ' . $name,
                Str::snake($name) . '.create' => 'Create ' . $name,
                Str::snake($name) . '.edit' => 'Edit ' . $name,
                Str::snake($name) . '.delete' => 'Delete ' . $name,
            ],
            'roles' => [
                Str::snake($name) . '-manager' => [
                    'name' => Str::title($name) . ' Manager',
                    'permissions' => [
                        Str::snake($name) . '.view',
                        Str::snake($name) . '.create',
                        Str::snake($name) . '.edit',
                        Str::snake($name) . '.delete',
                    ],
                ],
                Str::snake($name) . '-viewer' => [
                    'name' => Str::title($name) . ' Viewer',
                    'permissions' => [
                        Str::snake($name) . '.view',
                    ],
                ],
            ],
            'migrations' => [
                'database/migrations/2024_03_21_000000_create_' . Str::snake($name) . '_table.php',
            ],
            'seeders' => [
                'database/seeders/' . $name . 'DatabaseSeeder.php',
            ],
        ];

        File::put("{$modulePath}/config/module.php", '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($config, true) . ';' . PHP_EOL);
    }

    protected function createExampleLivewire($name, $modulePath)
    {
        // Create colocated Livewire/Volt component (PHP class and Blade view)
        $componentDir = $modulePath . '/resources/components';
        if (!is_dir($componentDir)) {
            \Illuminate\Support\Facades\File::makeDirectory($componentDir, 0755, true, true);
        }
        $componentClassPath = $componentDir . "/ExampleComponent.php";
        $componentBladePath = $componentDir . "/ExampleComponent.blade.php";

        $stubsPath = __DIR__ . '/../Stubs/resources/components';
        $classStub = file_get_contents($stubsPath . '/ExampleComponent.php.stub');
        $bladeStub = file_get_contents($stubsPath . '/ExampleComponent.blade.php.stub');
        $classContent = str_replace(['{{ module }}'], [$name], $classStub);
        $bladeContent = str_replace(['{{ module }}'], [$name], $bladeStub);
        \Illuminate\Support\Facades\File::put($componentClassPath, $classContent);
        \Illuminate\Support\Facades\File::put($componentBladePath, $bladeContent);
    }
} 