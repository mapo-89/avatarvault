<?php

return [
    'default_avatar' => 'vendor/avatar-manager/default.png',
    'api_keys' => [env('AVATAR_API_KEY')],
    'disk' => env('AVATAR_DISK', 'public'),
    'profile_photo_disk' => env('AVATAR_DISK', 'public'),
    'media_collection' => 'avatars',
];
