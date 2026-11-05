<?php

namespace App\Http\Controllers;

use App\Services\AliasAwareUserProvider;
use Illuminate\Support\Facades\Storage;
use Mapo89\LaravelAvatarManager\Facades\AvatarManager;
use Mapo89\LaravelAvatarManager\Http\Controllers\AvatarController as PackageAvatarController;
use Symfony\Component\HttpFoundation\Response;

class PublicAvatarController extends Controller
{
    public function __invoke(string $hash, AliasAwareUserProvider $users, PackageAvatarController $avatars): Response
    {
        $user = $users->findByEmailHash($hash);
        if ($user === null && strlen($hash) === 64) {
            $disk = Storage::disk(config('avatar-manager.disk', 'public'));
            $index = 'avatars/hashes/'.strtolower($hash).'.md5';
            if ($disk->exists($index)) {
                $canonical = $disk->get($index);
                if (is_string($canonical) && preg_match('/\A[a-f0-9]{32}\z/', $canonical)) {
                    $hash = $canonical;
                }
            }
        }

        return $avatars->show($user ? AvatarManager::hash($user->email) : $hash);
    }
}
