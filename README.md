# Egerstudios Tenant Modules

A flexible module system for Laravel multi-tenant applications. This package provides a robust foundation for building modular, multi-tenant applications with Laravel.

## Features

- Module creation and management
- Tenant-specific module activation
- Automatic permission and role management
- Navigation system integration
- Asset management
- Migration and seeder support
- Event system for module lifecycle

## Installation

You can install the package via composer:

```bash
composer require egerstudios/tenant-modules
```

After installing the package, publish the configuration and stubs:

```bash
php artisan vendor:publish --provider="Egerstudios\TenantModules\TenantModulesServiceProvider"
```

## Usage

### Creating a Module

To create a new module, use the artisan command:

```bash
php artisan module:make ModuleName
```

This will create a new module with the following structure:

```
app/Modules/ModuleName/
├── config/
│   ├── module.php
│   ├── navigation.yaml
│   └── permissions.yaml
├── database/
│   ├── migrations/
│   └── seeders/
└── resources/
    ├── views/
    ├── css/
    └── js/
```

### Activating a Module

To activate a module for a tenant:

```bash
php artisan module:activate ModuleName --domain=tenant.example.com
```

### Deactivating a Module

To deactivate a module for a tenant:

```bash
php artisan module:deactivate ModuleName --domain=tenant.example.com
```

### Module Configuration

Each module has three main configuration files:

1. `config/module.php`: Core module configuration
2. `config/navigation.yaml`: Module navigation structure
3. `config/permissions.yaml`: Module permissions and roles

### Events

The package fires the following events:

- `ModuleActivated`: When a module is activated for a tenant
- `ModuleDeactivated`: When a module is deactivated for a tenant

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email info@egerstudios.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. 