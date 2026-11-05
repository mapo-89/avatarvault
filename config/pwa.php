<?php

declare(strict_types=1);

return [
    'install-button' => true,

    'manifest' => [
        'name' => (string) config('app.name', 'AvatarVault'),
        'short_name' => (string) config('app.name', 'AvatarVault'),
        'background_color' => '#f8f6fc',
        'display' => 'standalone',
        'description' => 'AvatarVault Progressive Web App.',
        'theme_color' => '#7134dd',
        'icons' => [
            [
                'src' => '/images/brand/icon.svg',
                'sizes' => 'any',
                'type' => 'image/svg+xml',
            ],
        ],
    ],

    'debug' => env('APP_DEBUG', false),

    'livewire-app' => false,
];
