<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
