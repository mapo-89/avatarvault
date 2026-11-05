<?php

return [
    'route_prefix' => 'admin',
    'auth' => [
        'registration_enabled' => env('REGISTRATION_ENABLED', true),
        'email_verification_enabled' => env('EMAIL_VERIFICATION_ENABLED', false),
        'socialite' => [
            'providers' => ['oidc' => ['enabled' => env('SOCIAL_OIDC_ENABLED', false)]],
        ],
    ],
    'files' => [
        'disk' => env('AVATAR_DISK', 'public'),
        'avatar' => [
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'max_upload_size' => 2048,
        ],
    ],
];
