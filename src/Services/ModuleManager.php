<?php

namespace Egerstudios\TenantModules\Services;

use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;
use Egerstudios\TenantModules\Models\ModuleLog;
use Egerstudios\TenantModules\Events\ModuleStateChanged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Exception;
use Egerstudios\TenantModules\Events\ModuleEnabled;
use Egerstudios\TenantModules\Events\ModuleDisabled;
use Illuminate\Support\Facades\Event;

/**
 * ModuleManager - Central Service for Multi-Tenant Module State Management
 * 
 * This service acts as the single source of truth for all module operations within a multi-tenant 
 * Laravel application. It provides a centralized, transactional approach to enabling and disabling 
 * modules for specific tenants, ensuring data consistency and proper event handling.
 * 
 * Key Responsibilities:
 * - Module activation/deactivation with full rollback support
 * - Automated database migrations and seeding for module-specific schemas
 * - Dynamic permission management based on user roles
 * - Comprehensive audit logging of all module operations
 * - Event dispatching for decoupled system integration
 * 
 * Architecture Notes:
 * - Uses database transactions to ensure atomicity of operations
 * - Implements the Command pattern for module operations
 * - Leverages Laravel's tenant context switching for isolated database operations
 * - Integrates with Spatie Permission package for role-based access control
 * 
 * @package Egerstudios\TenantModules\Services
 * @author Your Name
 * @version 1.0.0
 * @since 1.0.0
 */
class ModuleManager
{
    /**
     * Enable a module for a specific tenant with full setup and permission management
     * 
     * This method performs a complete module activation workflow including:
     * 1. Database transaction management for data consistency
     * 2. Module record creation/retrieval
     * 3. Pivot table state management
     * 4. Module-specific database migrations and seeding
     * 5. User permission updates based on existing roles
     * 6. Comprehensive audit logging
     * 7. Event dispatching for system integration
     * 
     * The operation is fully transactional - if any step fails, all changes are rolled back
     * to maintain database consistency.
     * 
     * @param Tenant $tenant The tenant instance for which to enable the module
     * @param string $moduleName The name/identifier of the module to enable
     * 
     * @return bool True if module was successfully enabled, false on failure
     * 
     * @throws Exception When database operations fail (handled internally with rollback)
     * 
     * @example
     * ```php
     * $moduleManager = new ModuleManager();
     * $tenant = Tenant::find(1);
     * $success = $moduleManager->enableModule($tenant, 'inventory-management');
     * 
     * if ($success) {
     *     echo "Module enabled successfully";
     * } else {
     *     echo "Failed to enable module - check logs for details";
     * }
     * ```
     */
    public function enableModule(Tenant $tenant, string $moduleName): bool
    {
        try {
            // Start database transaction to ensure atomicity
            DB::beginTransaction();

            Log::info("Enabling module {$moduleName} for tenant {$tenant->id}");

            // Step 1: Ensure module record exists in the system
            $module = $this->findOrCreateModule($moduleName);
            
            // Step 2: Update the many-to-many relationship state
            $this->updateModuleState($tenant, $module, true);
            
            // Step 3: Execute module-specific database setup (migrations, seeders)
            $this->runModuleSetup($tenant, $moduleName);
            
            // Step 4: Grant appropriate permissions to existing tenant users
            $this->updateUserPermissions($tenant, $moduleName);
            
            // Step 5: Create audit trail entry
            $this->logModuleAction($tenant, $moduleName, 'enabled');
            
            // Commit all database changes
            DB::commit();
            
            // Step 6: Dispatch event for external system integration (outside transaction)
            Event::dispatch(new ModuleEnabled($tenant, $moduleName, [
                'description' => $module->description,
                'version' => $module->version,
                'is_core' => $module->is_core
            ]));
            
            Log::info("Successfully enabled module {$moduleName} for tenant {$tenant->id}");
            return true;
            
        } catch (Exception $e) {
            // Rollback all changes on any failure
            DB::rollBack();
            Log::error("Failed to enable module {$moduleName} for tenant {$tenant->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Disable a module for a specific tenant with proper cleanup and auditing
     * 
     * This method safely deactivates a module for a tenant by:
     * 1. Validating module existence
     * 2. Updating the pivot table relationship state
     * 3. Creating audit log entries
     * 4. Dispatching events for system integration
     * 
     * Note: This method does not perform destructive operations like dropping tables
     * or removing data - it only marks the module as inactive. This preserves data
     * integrity and allows for easy re-activation.
     * 
     * @param Tenant $tenant The tenant instance for which to disable the module
     * @param string $moduleName The name/identifier of the module to disable
     * 
     * @return bool True if module was successfully disabled, false on failure
     * 
     * @throws Exception When database operations fail (handled internally with rollback)
     * 
     * @example
     * ```php
     * $moduleManager = new ModuleManager();
     * $tenant = Tenant::find(1);
     * $success = $moduleManager->disableModule($tenant, 'inventory-management');
     * 
     * if ($success) {
     *     echo "Module disabled successfully";
     * } else {
     *     echo "Failed to disable module - check logs for details";
     * }
     * ```
     */
    public function disableModule(Tenant $tenant, string $moduleName): bool
    {
        try {
            // Start database transaction for consistency
            DB::beginTransaction();

            Log::info("Disabling module {$moduleName} for tenant {$tenant->id}");

            // Validate module exists before attempting to disable
            $module = Module::where('name', $moduleName)->first();
            if (!$module) {
                Log::warning("Module {$moduleName} not found");
                return false;
            }
            
            // Update the pivot table to mark module as inactive
            $this->updateModuleState($tenant, $module, false);
            
            // Create audit trail entry
            $this->logModuleAction($tenant, $moduleName, 'disabled');
            
            // Commit the changes
            DB::commit();
            
            // Dispatch event for external system integration (outside transaction)
            Event::dispatch(new ModuleDisabled($tenant, $module));
            
            Log::info("Successfully disabled module {$moduleName} for tenant {$tenant->id}");
            return true;
            
        } catch (Exception $e) {
            // Rollback changes on failure
            DB::rollBack();
            Log::error("Failed to disable module {$moduleName} for tenant {$tenant->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check if a specific module is currently active for a tenant
     * 
     * This method queries the many-to-many relationship between tenants and modules
     * to determine if a module is currently enabled and active.
     * 
     * @param Tenant $tenant The tenant instance to check
     * @param string $moduleName The name/identifier of the module to check
     * 
     * @return bool True if the module is enabled and active, false otherwise
     * 
     * @example
     * ```php
     * $moduleManager = new ModuleManager();
     * $tenant = Tenant::find(1);
     * 
     * if ($moduleManager->isModuleEnabled($tenant, 'inventory-management')) {
     *     echo "Inventory module is active";
     * } else {
     *     echo "Inventory module is not active";
     * }
     * ```
     */
    public function isModuleEnabled(Tenant $tenant, string $moduleName): bool
    {
        return $tenant->modules()
            ->where('name', $moduleName)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Retrieve all currently enabled modules for a specific tenant
     * 
     * This method returns an array of module names that are currently active
     * for the specified tenant. Useful for determining available functionality
     * or building dynamic navigation menus.
     * 
     * @param Tenant $tenant The tenant instance to query
     * 
     * @return array<string> Array of module names that are currently enabled
     * 
     * @example
     * ```php
     * $moduleManager = new ModuleManager();
     * $tenant = Tenant::find(1);
     * $enabledModules = $moduleManager->getEnabledModules($tenant);
     * 
     * // Result might be: ['inventory-management', 'user-management', 'reporting']
     * foreach ($enabledModules as $moduleName) {
     *     echo "Active module: {$moduleName}\n";
     * }
     * ```
     */
    public function getEnabledModules(Tenant $tenant): array
    {
        return $tenant->modules()
            ->wherePivot('is_active', true)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Find an existing module or create a new module record if it doesn't exist
     * 
     * This method implements the "First or Create" pattern to ensure a module
     * record exists in the database before attempting to enable it for a tenant.
     * If the module doesn't exist, it creates a new record with default values.
     * 
     * @param string $moduleName The name/identifier of the module
     * 
     * @return Module The found or newly created Module model instance
     * 
     * @example
     * ```php
     * // This will either find existing module or create new one with defaults
     * $module = $this->findOrCreateModule('new-feature-module');
     * echo $module->name; // 'new-feature-module'
     * echo $module->version; // '1.0.0' (default)
     * ```
     */
    protected function findOrCreateModule(string $moduleName): Module
    {
        return Module::firstOrCreate(
            ['name' => $moduleName], // Search criteria
            [
                // Default values for new records
                'description' => "Module {$moduleName}",
                'version' => '1.0.0',
                'is_core' => false
            ]
        );
    }

    /**
     * Update the module activation state in the tenant-module pivot table
     * 
     * This method handles the many-to-many relationship between tenants and modules,
     * updating the pivot table with appropriate timestamps and activation status.
     * 
     * For activation: Uses syncWithoutDetaching to avoid duplicate entries while
     * updating the pivot attributes.
     * 
     * For deactivation: Uses updateExistingPivot to modify the existing relationship
     * without creating new records.
     * 
     * @param Tenant $tenant The tenant instance
     * @param Module $module The module instance
     * @param bool $isActive Whether the module should be marked as active or inactive
     * 
     * @return void
     */
    protected function updateModuleState(Tenant $tenant, Module $module, bool $isActive): void
    {
        if ($isActive) {
            // Enable the module - sync without detaching existing relationships
            $tenant->modules()->syncWithoutDetaching([
                $module->id => [
                    'is_active' => true,
                    'activated_at' => now(),
                    'deactivated_at' => null
                ]
            ]);
        } else {
            // Disable the module - update existing pivot record
            $tenant->modules()->updateExistingPivot($module->id, [
                'is_active' => false,
                'deactivated_at' => now(),
            ]);
        }
    }

    /**
     * Execute module-specific database setup operations within tenant context
     * 
     * This method runs within the tenant's database context to perform:
     * 1. Module-specific database migrations using Laravel's tenant-aware migration command
     * 2. Database seeding with module-specific roles, permissions, and default data
     * 
     * The tenant context switching ensures that all database operations are performed
     * on the correct tenant database, maintaining proper data isolation in multi-tenant
     * architecture.
     * 
     * Directory Structure Expected:
     * - modules/{ModuleName}/database/migrations/ - Contains migration files
     * - modules/{ModuleName}/database/seeders/ - Contains seeder classes
     * 
     * @param Tenant $tenant The tenant instance (for context switching)
     * @param string $moduleName The name of the module to set up
     * 
     * @return void
     * 
     * @throws Exception If migration or seeding operations fail
     */
    protected function runModuleSetup(Tenant $tenant, string $moduleName): void
    {
        // Execute within tenant database context
        $tenant->run(function () use ($moduleName) {
            Log::info("Running setup for module {$moduleName}");

            // Step 1: Run database migrations for the module
            $migrationPath = base_path("modules/{$moduleName}/database/migrations");
            if (is_dir($migrationPath)) {
                Log::info("Running tenants:migrate for module {$moduleName}");
                
                // Use tenant-aware migration command with specific path
                Artisan::call('tenants:migrate', [
                    '--path' => $migrationPath,
                    '--realpath' => true,  // Use absolute path
                    '--force' => true      // Skip confirmation prompts
                ]);
            } else {
                Log::info("No migration directory found for module {$moduleName}");
            }

            // Step 2: Run database seeders for roles, permissions, and default data
            $seederDir = base_path("modules/{$moduleName}/database/seeders");
            if (is_dir($seederDir)) {
                // Process all PHP files in the seeders directory
                foreach (glob($seederDir . '/*.php') as $seederFile) {
                    // Construct the fully qualified class name
                    $seederClass = "Modules\\{$moduleName}\\Database\\Seeders\\" . pathinfo($seederFile, PATHINFO_FILENAME);
                    
                    if (class_exists($seederClass)) {
                        Log::info("Seeding: {$seederClass}");
                        
                        // Use tenant-aware seeding command
                        Artisan::call('tenants:seed', [
                            '--class' => $seederClass,
                            '--force' => true  // Skip confirmation prompts
                        ]);
                    }
                }
            } else {
                Log::info("No seeder directory found for module {$moduleName}");
            }
        });
    }

    /**
     * Update user permissions when a module is enabled, based on existing roles
     * 
     * This method implements a role-based permission assignment strategy:
     * 1. Retrieves all module-specific permissions (using naming convention: moduleName.action)
     * 2. Maps permissions to roles based on a predefined hierarchy
     * 3. Assigns permissions to users based on their current roles
     * 4. Assigns module-specific roles for granular access control
     * 
     * Role-Permission Hierarchy:
     * - Owner/Admin: Full access to all module permissions
     * - Manager: All permissions except delete and manage operations
     * - Member: View-only permissions
     * 
     * Module-Specific Roles:
     * - {module}-manager: For Owner/Admin users
     * - {module}-user: For other users
     * 
     * @param Tenant $tenant The tenant instance (for context and user retrieval)
     * @param string $moduleName The name of the module being enabled
     * 
     * @return void
     */
    protected function updateUserPermissions(Tenant $tenant, string $moduleName): void
    {
        // Get all users for this tenant
        $users = $tenant->users;

        // Execute within tenant database context for permission operations
        $tenant->run(function () use ($users, $moduleName) {
            // Retrieve all permissions related to this module (naming convention: moduleName.action)
            $modulePermissions = Permission::where('name', 'like', "{$moduleName}.%")->get();

            if ($modulePermissions->isEmpty()) {
                Log::info("No permissions found for module {$moduleName}, checking for permission seeder");
                
                // Check if there's a permission seeder for this module
                $seederClass = "Modules\\{$moduleName}\\Database\\Seeders\\PermissionSeeder";
                if (class_exists($seederClass)) {
                    Log::info("Running permission seeder for module {$moduleName}");
                    Artisan::call('tenants:seed', [
                        '--class' => $seederClass,
                        '--force' => true
                    ]);
                    
                    // Re-fetch permissions after seeding
                    $modulePermissions = Permission::where('name', 'like', "{$moduleName}.%")->get();
                    
                    if ($modulePermissions->isEmpty()) {
                        Log::warning("Still no permissions found for module {$moduleName} after running seeder");
                        return;
                    }
                } else {
                    Log::warning("No permission seeder found for module {$moduleName}");
                    return;
                }
            }

            // Define role-based permission mapping with hierarchical access
            $rolePermissionMap = [
                // Full administrative access
                'Owner' => $modulePermissions->pluck('name')->toArray(),
                'Admin' => $modulePermissions->pluck('name')->toArray(),
                
                // Management access (excluding destructive operations)
                'Manager' => $modulePermissions->filter(function($permission) {
                    return !str_ends_with($permission->name, '.delete') 
                        && !str_ends_with($permission->name, '.manage');
                })->pluck('name')->toArray(),
                
                // Read-only access
                'Member' => $modulePermissions->filter(function($permission) {
                    return str_ends_with($permission->name, '.view');
                })->pluck('name')->toArray(),
            ];

            // Process each user in the tenant
            foreach ($users as $user) {
                // Get user's current roles
                $currentRoles = $user->getRoleNames();

                // Assign permissions based on user's roles
                foreach ($currentRoles as $roleName) {
                    if (isset($rolePermissionMap[$roleName])) {
                        // Grant all permissions for this role level
                        $user->givePermissionTo($rolePermissionMap[$roleName]);
                    }
                }

                // Assign module-specific roles for granular control
                if ($currentRoles->contains('Owner') || $currentRoles->contains('Admin')) {
                    // High-privilege users get manager role for this module
                    $user->assignRole("{$moduleName}-manager");
                } else {
                    // Standard users get basic user role for this module
                    $user->assignRole("{$moduleName}-user");
                }
            }
        });
    }

    /**
     * Create an audit log entry for module state changes
     * 
     * This method maintains a comprehensive audit trail of all module operations,
     * recording who did what and when for compliance and debugging purposes.
     * 
     * The audit log captures:
     * - Which tenant the operation was performed on
     * - Which module was affected
     * - What action was taken (enabled/disabled)
     * - When the action occurred
     * 
     * @param Tenant $tenant The tenant instance affected by the action
     * @param string $moduleName The name of the module that was modified
     * @param string $action The action that was performed ('enabled' or 'disabled')
     * 
     * @return void
     * 
     * @see ModuleLog Model for the database schema and additional audit features
     */
    protected function logModuleAction(Tenant $tenant, string $moduleName, string $action): void
    {
        // Execute within tenant database context
        $tenant->run(function () use ($tenant, $moduleName, $action) {
            ModuleLog::create([
                'tenant_id' => $tenant->id,
                'module_name' => $moduleName,
                'action' => $action,
                'occurred_at' => now()
            ]);
        });
    }
}