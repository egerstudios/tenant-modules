<?php

namespace Egerstudios\TenantModules\Tests;

use Illuminate\Support\Facades\File;
use Egerstudios\TenantModules\ModuleManager;

class ModuleManagerTest extends TestCase
{
    protected ModuleManager $manager;
    protected string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->modulesPath = config('modules.path');
        $this->manager = new ModuleManager();
        
        // Create test module directory
        File::makeDirectory($this->modulesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up test module directory
        File::deleteDirectory($this->modulesPath);
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_discover_modules()
    {
        // Create a test module
        $this->createTestModule('TestModule');

        $modules = $this->manager->getModules();
        
        $this->assertArrayHasKey('TestModule', $modules);
        $this->assertEquals('Test Module', $modules['TestModule']['name']);
    }

    /** @test */
    public function it_can_activate_a_module()
    {
        $this->createTestModule('TestModule');
        
        $result = $this->manager->activate('test-tenant', 'TestModule');
        
        $this->assertTrue($result);
        $this->assertTrue($this->manager->isActive('test-tenant', 'TestModule'));
    }

    /** @test */
    public function it_can_deactivate_a_module()
    {
        $this->createTestModule('TestModule');
        
        $this->manager->activate('test-tenant', 'TestModule');
        $result = $this->manager->deactivate('test-tenant', 'TestModule');
        
        $this->assertTrue($result);
        $this->assertFalse($this->manager->isActive('test-tenant', 'TestModule'));
    }

    protected function createTestModule(string $name): void
    {
        $modulePath = "{$this->modulesPath}/{$name}";
        File::makeDirectory($modulePath, 0755, true);
        
        // Create module.php
        File::put("{$modulePath}/config/module.php", <<<PHP
<?php

return [
    'name' => '{$name}',
    'description' => 'Test Module',
    'version' => '1.0.0',
    'assets' => [
        'styles' => [],
        'scripts' => [],
    ],
    'migrations' => [],
    'seeders' => [],
];
PHP
        );
    }
} 