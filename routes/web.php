<?php

use App\Http\Controllers\AvatarController;
use App\Http\Controllers\EmailAliasController;
use App\Http\Controllers\PublicAvatarController;
use CorePanel\Support\Socialite\SocialiteProviderRegistry;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (SocialiteProviderRegistry $providers) => Inertia::render('Welcome', [
    'canRegister' => (bool) config('core-panel.auth.registration_enabled'),
    'socialProviders' => $providers->enabledProviders(),
]))->name('home');

Route::get('/avatar/{hash}', PublicAvatarController::class)->where('hash', '(?:[a-fA-F0-9]{32}|[a-fA-F0-9]{64})')->name('avatar.show');

// Retain the old Authentik entrypoint while using CorePanel's OIDC callback flow.
Route::get('/auth/authentik', fn () => redirect()->route('socialite.redirect', ['provider' => 'oidc']))->name('auth.redirect');
Route::get('/user/profile', fn () => redirect()->route('profile.show'));

Route::middleware(['auth', 'core-panel.verified'])->group(function (): void {
    Route::post('/profile/email-aliases', [EmailAliasController::class, 'store'])->middleware('throttle:email-aliases-write')->name('email-aliases.store');
    Route::post('/profile/email-aliases/{alias}/resend', [EmailAliasController::class, 'resend'])->middleware('throttle:email-aliases-resend')->name('email-aliases.resend');
    Route::get('/profile/email-aliases/{alias}/{hash}/verify', [EmailAliasController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('email-aliases.verify');
    Route::delete('/profile/email-aliases/{alias}', [EmailAliasController::class, 'destroy'])->name('email-aliases.destroy');

    Route::delete('/profile/account', [AvatarController::class, 'destroyAccount'])->name('account.destroy');
    Route::get('/dashboard', [AvatarController::class, 'index'])->name('dashboard');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->name('avatar.store');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
});
