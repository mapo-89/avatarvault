<?php

namespace App\Http\Controllers;

use App\Services\AliasAwareUserProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mapo89\LaravelAvatarManager\Facades\AvatarManager;
use Mapo89\LaravelAvatarManager\Http\Controllers\Api\UploadController;

class ApiAvatarUploadController extends Controller
{
    public function __invoke(Request $request, AliasAwareUserProvider $users, UploadController $uploads): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $user = $users->findByEmailHash(AvatarManager::hash($validated['email']));
        if ($user !== null) {
            $request->merge(['email' => $user->email]);
        }

        return $uploads->upload($request);
    }
}
