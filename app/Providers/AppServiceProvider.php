<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        $host = request()->header('host', '');
        $forwardedHost = request()->header('x-forwarded-host', '');
        $forwardedProto = request()->header('x-forwarded-proto', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');

        if (
            $forwardedProto === 'https' ||
            (isset($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], 'https')) ||
            str_contains($host, 'ngrok') ||
            str_contains($forwardedHost, 'ngrok') ||
            str_contains($host, 'trycloudflare.com') ||
            str_contains($forwardedHost, 'trycloudflare.com')
        ) {
            URL::forceScheme('https');
            request()->server->set('HTTPS', 'on');
            request()->server->set('SERVER_PORT', 443);
            request()->headers->set('X-Forwarded-Port', 443);
            request()->headers->set('X-Forwarded-Proto', 'https');
        }
    }
}
