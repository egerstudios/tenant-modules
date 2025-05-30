# Tenant Modules for Laravel

A Laravel package that provides multi-tenant module management capabilities, allowing you to create, enable, disable, and manage modules per tenant in your Laravel application.

## Installation

1. Install the package via Composer:

```bash
composer require egerstudios/tenant-modules
```

2. Publish the configuration and migrations:

```bash
php artisan vendor:publish --provider="Egerstudios\TenantModules\TenantModulesServiceProvider"
```

3. Run the migrations:

```bash
php artisan migrate
```

## Configuration

The package configuration is published to `config/tenant-modules.php`. Here are the main configuration options:

```php
return [
    'path' => 'modules', // Base path for modules
    'namespace' => 'Modules', // Base namespace for modules
    'provider' => 'Providers\\ModuleServiceProvider', // Default service provider name
    'middleware' => [
        'enabled' => true, // Enable/disable middleware
        'prefix' => 'module', // URL prefix for module routes
    ],
];
```

## Module Structure

Each module follows this structure:

```
modules/
└── ModuleName/
    ├── config/
    │   ├── module.php
    │   └── navigation.yaml
    ├── database/
    │   └── migrations/
    ├── resources/
    │   ├── views/
    │   └── lang/
    ├── routes/
    │   └── web.php
    ├── src/
    │   ├── Controllers/
    │   ├── Models/
    │   └── Services/
    └── composer.json
```

### Module Configuration

Each module requires a `config/module.php` file:

```php
return [
    'name' => 'ModuleName',
    'enabled' => true,
    'description' => 'Module description',
    'version' => '1.0.0',
];
```

### Navigation Configuration

Modules can define their navigation in `config/navigation.yaml`:

```yaml
items:
  - label: Module Name
    icon: module
    route: module.index
    permission: module.view
    children:
      - label: Sub Item
        route: module.sub
        permission: module.view
```

## Available Commands

### Create a New Module

```bash
php artisan module:make ModuleName
```

Options:
- `--description`: Module description
- `--version`: Module version
- `--force`: Overwrite existing module

### List Modules

```bash
php artisan module:list
```

### Enable a Module

```bash
php artisan module:enable ModuleName
```

Options:
- `--tenant`: Specific tenant ID
- `--all-tenants`: Enable for all tenants

### Disable a Module

```bash
php artisan module:disable ModuleName
```

Options:
- `--tenant`: Specific tenant ID
- `--all-tenants`: Disable for all tenants

### Delete a Module

```bash
php artisan module:delete ModuleName
```

Options:
- `--force`: Force deletion without confirmation

### Build Module Assets

```bash
php artisan module:build ModuleName
```

## Helper Functions

The package provides several helper functions:

### Module Path

```php
module_path('ModuleName', 'path/to/file');
```

### Module Namespace

```php
module_namespace('ModuleName', 'Path\\To\\Class');
```

### Module Service Provider

```php
module_provider('ModuleName');
```

### Check Module Status

```php
module_enabled('ModuleName');
module_disabled('ModuleName');
```

### Get Module Information

```php
modules(); // Get all modules
modules('ModuleName'); // Get specific module info
```

## Middleware

The package includes a middleware to protect module routes:

```php
Route::middleware(['web', 'auth', 'module:ModuleName'])->group(function () {
    // Module routes
});
```

## Events

The package fires several events:

- `ModuleEnabled`: When a module is enabled
- `ModuleDisabled`: When a module is disabled
- `ModuleDeleted`: When a module is deleted

## Best Practices

1. Always use the helper functions to reference module paths and namespaces
2. Keep module-specific code within the module directory
3. Use the navigation configuration for consistent UI
4. Implement proper permissions for module access
5. Use the middleware to protect module routes

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. 