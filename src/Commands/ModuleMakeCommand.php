<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleMakeCommand extends Command
{
    protected $signature = 'module:make {name : The name of the module}';
    protected $description = 'Create a new module';

    public function handle()
    {
        $name = $this->argument('name');
        $modulePath = base_path(config('modules.path')) . '/' . $name;
        $stubsPath = __DIR__ . '/../Stubs';

        if (File::exists($modulePath)) {
            $this->error("Module {$name} already exists!");
            return 1;
        }

        // Create module directory structure
        $this->createDirectoryStructure($modulePath);

        // Create module files from stubs
        $this->createModuleFiles($name, $modulePath, $stubsPath);

        $this->info("Module {$name} created successfully!");
        return 0;
    }

    protected function createDirectoryStructure(string $modulePath): void
    {
        $directories = [
            'app/Http/Controllers',
            'app/Models',
            'app/Services',
            'app/Events',
            'app/Listeners',
            'app/Providers',
            'app/Http/Middleware',
            'app/Http/Requests',
            'app/Http/Resources',
            'app/Exceptions',
            'config',
            'database/migrations',
            'database/seeders',
            'resources/views',
            'resources/css',
            'resources/js',
            'routes',
            'tests/Feature',
            'tests/Unit',
        ];

        foreach ($directories as $directory) {
            File::makeDirectory($modulePath . '/' . $directory, 0755, true);
        }
    }

    protected function createModuleFiles(string $name, string $modulePath, string $stubsPath): void
    {
        $replacements = [
            '{{ module }}' => $name,
            '{{ module_snake }}' => Str::snake($name),
            '{{ description }}' => "The {$name} module",
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
            "$stubsPath/database/seeders/DatabaseSeeder.php.stub",
            "$modulePath/database/seeders/{$name}DatabaseSeeder.php",
            $replacements
        );

        // Create default view
        $this->createFileFromStub(
            "$stubsPath/resources/views/welcome.blade.php.stub",
            "$modulePath/resources/views/welcome.blade.php",
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
            "$stubsPath/app/Models/ExampleModel.php.stub",
            "$modulePath/app/Models/ExampleModel.php",
            $replacements
        );

        // Create example service
        $this->createFileFromStub(
            "$stubsPath/app/Services/ExampleService.php.stub",
            "$modulePath/app/Services/ExampleService.php",
            $replacements
        );

        // Create example event
        $this->createFileFromStub(
            "$stubsPath/app/Events/ExampleEvent.php.stub",
            "$modulePath/app/Events/ExampleEvent.php",
            $replacements
        );

        // Create example controller
        $this->createFileFromStub(
            "$stubsPath/app/Http/Controllers/ExampleController.php.stub",
            "$modulePath/app/Http/Controllers/ExampleController.php",
            $replacements
        );

        // Create service provider
        $this->createFileFromStub(
            $stubsPath . '/app/Providers/ModuleServiceProvider.php.stub',
            $modulePath . '/app/Providers/ModuleServiceProvider.php',
            $replacements
        );

        // Create example Livewire/Volt colocated component
        $this->createExampleLivewire($name, $modulePath);

        // Create module.json
        $this->createFileFromStub(
            $stubsPath . '/module.json.stub',
            $modulePath . '/module.json',
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