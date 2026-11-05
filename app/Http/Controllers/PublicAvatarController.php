<?php

namespace App\Http\Controllers;

use App\Services\AliasAwareUserProvider;
use Mapo89\LaravelAvatarManager\Facades\AvatarManager;
use Mapo89\LaravelAvatarManager\Http\Controllers\AvatarController as PackageAvatarController;
use Symfony\Component\HttpFoundation\Response;

class PublicAvatarController extends Controller
{
    public function __invoke(string $hash, AliasAwareUserProvider $users, PackageAvatarController $avatars): Response
    {
        $user = $users->findByEmailHash($hash);

        return $avatars->show($user ? AvatarManager::hash($user->email) : $hash);
    }
}
