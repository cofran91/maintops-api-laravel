<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    'api_path' => 'api/v1',

    'api_domain' => null,

    'export_path' => 'public/api.json',

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => <<<'DESCRIPTION'
Versioned API for MaintOps.

Authentication:
- Login returns a Laravel Sanctum personal access token.
- Protected endpoints require the Authorization: Bearer {token} header.
- Logout revokes the token used by the current request.

Roles:
- The API uses Spatie Laravel Permission for role-based access control.
- Initial roles: super_admin, admin, workshop_manager, advisor, technician.
- The development seed creates admin@maint.test with the super_admin role.

Documentation:
- The OpenAPI specification is exported to public/api.json with php artisan scramble:export.
- /docs serves the exported file, so the documentation UI does not regenerate the spec on each request.
DESCRIPTION,
    ],

    'ui' => [
        'title' => 'MaintOps Laravel API Documentation',

        'theme' => 'light',

        'hide_try_it' => false,

        'hide_schemas' => true,

        'logo' => '',

        'try_it_credentials_policy' => 'include',

        'layout' => 'responsive',
    ],

    'servers' => null,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
];
