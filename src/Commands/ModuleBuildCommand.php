<?php

namespace Egerstudios\TenantModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModuleBuildCommand extends Command
{
    protected $signature = 'module:build {module? : The name of the module to build}';
    protected $description = 'Build module assets';

    public function handle()
    {
        $moduleName = $this->argument('module');
        $modulesPath = base_path(config('modules.path'));

        if ($moduleName) {
            $this->buildModule($moduleName);
        } else {
            foreach (File::directories($modulesPath) as $modulePath) {
                $this->buildModule(basename($modulePath));
            }
        }

        return 0;
    }

    protected function buildModule(string $moduleName): void
    {
        $modulePath = base_path(config('modules.path')) . '/' . $moduleName;
        $configPath = "{$modulePath}/config/module.php";

        if (!File::exists($configPath)) {
            $this->warn("Module {$moduleName} not found!");
            return;
        }

        $config = require $configPath;
        $assets = $config['assets'] ?? [];

        $this->info("Building assets for module {$moduleName}...");

        // Build CSS
        if (!empty($assets['styles'])) {
            $this->buildStyles($moduleName, $assets['styles']);
        }

        // Build JS
        if (!empty($assets['scripts'])) {
            $this->buildScripts($moduleName, $assets['scripts']);
        }

        $this->info("Module {$moduleName} assets built successfully!");
    }

    protected function buildStyles(string $moduleName, array $styles): void
    {
        $modulePath = base_path(config('modules.path')) . '/' . $moduleName;
        $outputPath = public_path(config('modules.assets.public')) . "/{$moduleName}/css";
        $outputFile = "{$outputPath}/module.css";

        if (!file_exists($outputPath)) {
            File::makeDirectory($outputPath, 0755, true, true);
        }

        $content = '';
        foreach ($styles as $style) {
            $stylePath = "{$modulePath}/{$style}";
            if (File::exists($stylePath)) {
                $content .= File::get($stylePath) . "\n";
            }
        }

        File::put($outputFile, $content);
    }

    protected function buildScripts(string $moduleName, array $scripts): void
    {
        $modulePath = base_path(config('modules.path')) . '/' . $moduleName;
        $outputPath = public_path(config('modules.assets.public')) . "/{$moduleName}/js";
        $outputFile = "{$outputPath}/module.js";

        if (!file_exists($outputPath)) {
            File::makeDirectory($outputPath, 0755, true, true);
        }

        $content = '';
        foreach ($scripts as $script) {
            $scriptPath = "{$modulePath}/{$script}";
            if (File::exists($scriptPath)) {
                $content .= File::get($scriptPath) . "\n";
            }
        }

        File::put($outputFile, $content);
    }
} 