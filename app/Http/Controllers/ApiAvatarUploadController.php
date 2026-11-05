<?php

namespace App\Http\Controllers;

use App\Services\AliasAwareUserProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mapo89\LaravelAvatarManager\Facades\AvatarManager;
use Mapo89\LaravelAvatarManager\Http\Controllers\Api\UploadController;
use RuntimeException;

class ApiAvatarUploadController extends Controller
{
    public function __invoke(Request $request, AliasAwareUserProvider $users, UploadController $uploads): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $user = $users->findByEmailHash(AvatarManager::hash($validated['email']));
        if ($user !== null) {
            $request->merge(['email' => $user->email]);
        }

        $response = $uploads->upload($request);
        if ($response->isSuccessful()) {
            $email = strtolower(trim($request->input('email')));
            if (! Storage::disk(config('avatar-manager.disk', 'public'))->put(
                'avatars/hashes/'.hash('sha256', $email).'.md5', AvatarManager::hash($email)
            )) {
                throw new RuntimeException('Unable to store avatar hash lookup.');
            }
        }

        return $response;
    }
}
