<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (config('barin.plugins', []) as $provider) {
            $this->app->register($provider);
        }
    }
}
