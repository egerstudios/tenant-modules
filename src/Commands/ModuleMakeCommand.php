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
        $stubsPath = __DIR__ . '/../../stubs';

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
            'config',
            'database/migrations',
            'database/seeders',
            'resources/views',
            'resources/css',
            'resources/js',
        ];

        foreach ($directories as $directory) {
            File::makeDirectory("{$modulePath}/{$directory}", 0755, true);
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
            "{$stubsPath}/config/module.php.stub",
            "{$modulePath}/config/module.php",
            $replacements
        );

        // Create navigation.yaml
        $this->createFileFromStub(
            "{$stubsPath}/config/navigation.yaml.stub",
            "{$modulePath}/config/navigation.yaml",
            $replacements
        );

        // Create permissions.yaml
        $this->createFileFromStub(
            "{$stubsPath}/config/permissions.yaml.stub",
            "{$modulePath}/config/permissions.yaml",
            $replacements
        );

        // Create database seeder
        $seederContent = $this->getStubContent("{$stubsPath}/database/seeders/DatabaseSeeder.php.stub", $replacements);
        File::put("{$modulePath}/database/seeders/{$name}DatabaseSeeder.php", $seederContent);
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
} 