<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-read', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?: 'public-read');
        });

        RateLimiter::for('public-write', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip() ?: 'public-write');
        });

        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'guest'));
            return Limit::perMinute(5)->by($request->ip().':'.$email);
        });
    }
}
