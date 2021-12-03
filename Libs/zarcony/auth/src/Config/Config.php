<?php
    return [
        'version'               => 'v1.0.0-Alpha',
        'prefix'                => env('ZARCONY_AUTH_PREFIX', 'api/auth'),
        'generalMiddleware'     => env('ZARCONY_GENERAL_MIDDLEWARE', 'api'),
        'secure_middleware'     => ['api', 'auth:api'],
        'admin_middleware'      => env('ADMIN_ROLE', 'role:admin'),
    ];
