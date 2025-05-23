<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modules Path
    |--------------------------------------------------------------------------
    |
    | This is the path where your modules will be located.
    |
    */
    'path' => 'modules',

    /*
    |--------------------------------------------------------------------------
    | Modules Namespace
    |--------------------------------------------------------------------------
    |
    | This is the namespace that will be used for your modules.
    |
    */
    'namespace' => 'Modules',

    /*
    |--------------------------------------------------------------------------
    | Module Service Provider
    |--------------------------------------------------------------------------
    |
    | This is the service provider class that will be used for your modules.
    |
    */
    'provider' => 'app\\Providers\\ModuleServiceProvider',

    /*
    |--------------------------------------------------------------------------
    | Module Service Provider Path
    |--------------------------------------------------------------------------
    |
    | This is the path where your module service providers will be located.
    |
    */
    'provider_path' => 'app/Providers',

    /*
    |--------------------------------------------------------------------------
    | Module Service Provider Namespace
    |--------------------------------------------------------------------------
    |
    | This is the namespace that will be used for your module service providers.
    |
    */
    'provider_namespace' => 'app\\Providers',

    /*
    |--------------------------------------------------------------------------
    | Module Assets
    |--------------------------------------------------------------------------
    |
    | These are the paths where module assets will be published to. You can
    | customize these paths to match your application's structure.
    |
    */
    'assets' => [
        'public' => 'public/modules',
        'views' => 'resources/views/modules',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Cache
    |--------------------------------------------------------------------------
    |
    | Enable or disable module caching. When enabled, module configurations
    | will be cached to improve performance.
    |
    */
    'cache' => [
        'enabled' => env('MODULES_CACHE_ENABLED', true),
        'key' => 'modules',
        'lifetime' => 60,
    ],
]; 