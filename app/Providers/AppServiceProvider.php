<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('barin.plugins', []) as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        Paginator::currentPathResolver(fn () => url()->current());
    }
}
