<?php

use App\Http\Controllers\AvatarController;
use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (SocialiteProviderRegistry $providers) => Inertia::render('Welcome', [
    'canRegister' => (bool) config('core-panel.auth.registration_enabled'),
    'socialProviders' => $providers->enabledProviders(),
]))->name('home');

// Retain the old Authentik entrypoint while using CorePanel's OIDC callback flow.
Route::get('/auth/authentik', fn () => redirect()->route('socialite.redirect', ['provider' => 'oidc']))->name('auth.redirect');
Route::get('/user/profile', fn () => redirect()->route('profile.show'));

Route::middleware(['auth', 'core-panel.verified'])->group(function (): void {
    Route::delete('/profile/account', [AvatarController::class, 'destroyAccount'])->name('account.destroy');
    Route::get('/dashboard', [AvatarController::class, 'index'])->name('dashboard');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->name('avatar.store');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
});
