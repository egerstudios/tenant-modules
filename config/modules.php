<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Path
    |--------------------------------------------------------------------------
    |
    | This is the path where your modules will be located. By default, it's set
    | to 'app/Modules', but you can change it to any path you prefer.
    |
    */
    'path' => env('MODULES_PATH', 'app/Modules'),

    /*
    |--------------------------------------------------------------------------
    | Module Namespace
    |--------------------------------------------------------------------------
    |
    | This is the namespace that will be used for your modules. By default,
    | it's set to 'App\Modules', but you can change it to match your
    | application's namespace structure.
    |
    */
    'namespace' => env('MODULES_NAMESPACE', 'App\\Modules'),

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