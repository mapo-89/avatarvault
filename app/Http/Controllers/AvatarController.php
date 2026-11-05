<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Mapo89\LaravelAvatarManager\Facades\AvatarManager;

class AvatarController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Avatar/Dashboard', [
            'emailAliases' => $user->emailAliases()->orderBy('id')->get()->map(fn ($alias) => [
                'id' => $alias->id, 'email' => $alias->email, 'verified' => $alias->verified_at !== null,
                'url' => $alias->verified_at !== null ? AvatarManager::url($alias->email) : null,
            ]),
            'aliasLimit' => config('avatarvault.email_aliases.max_per_user'),
            'avatar' => [
                'url' => AvatarManager::url($user->email),
                'hash' => AvatarManager::hash($user->email),
                'uploaded' => $user->getFirstMedia('avatars') !== null || filled($user->profile_photo_path),
                'version' => $user->getFirstMedia('avatars')?->uuid ?? (string) $user->updated_at?->timestamp,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);
        $user = $request->user();
        $user->unsetRelation('media');
        $user->addMedia($validated['avatar'])->toMediaCollection('avatars');
        $this->removeLegacyPhoto($user);
        $this->removeApiPhoto($user->email);

        return back()->with('success', __('avatarvault.saved'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->unsetRelation('media');
        $user->clearMediaCollection('avatars');
        $this->removeLegacyPhoto($user);
        $this->removeApiPhoto($user->email);

        return back()->with('success', __('avatarvault.removed'));
    }

    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password:web']]);
        $user = $request->user();
        $this->removeLegacyPhoto($user);
        $this->removeApiPhoto($user->email);
        $user->tokens()->delete();
        Auth::guard('web')->logout();
        $user->forceDelete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function removeLegacyPhoto(User $user): void
    {
        if ($user->profile_photo_path) {
            Storage::disk(config('avatar-manager.profile_photo_disk'))->delete($user->profile_photo_path);
            $user->forceFill(['profile_photo_path' => null])->save();
        }
    }

    private function removeApiPhoto(string $email): void
    {
        $disk = Storage::disk(config('avatar-manager.disk'));
        $hash = AvatarManager::hash($email);

        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $extension) {
            $disk->delete('avatars/'.$hash.'.'.$extension);
        }
    }
}
