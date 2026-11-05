<?php

namespace App\Providers;

use App\Services\AliasAwareUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Mapo89\LaravelAvatarManager\Contracts\UserProviderInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserProviderInterface::class, AliasAwareUserProvider::class);
    }

    public function boot(): void
    {
        RateLimiter::for('email-aliases-write', fn (Request $request) => Limit::perHour(6)->by((string) $request->user()->id));
        RateLimiter::for('email-aliases-resend', fn (Request $request) => Limit::perHour(3)->by($request->user()->id.':'.$request->route('alias')));
        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
