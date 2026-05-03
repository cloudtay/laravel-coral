<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Module Layers
    |--------------------------------------------------------------------------
    |
    | Each absolute path is scanned for module directories containing a Module
    | class. Paths must live under app_path() so their namespace can be inferred
    | from Laravel's application namespace.
    |
    */
    'module_layers' => [
        \app_path('Modules'),
    ],
];
