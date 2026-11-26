<?php

use App\Http\Middleware\PublicHttps;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(PublicHttps::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['rate_token', 'ai_token']);
    })->create();
