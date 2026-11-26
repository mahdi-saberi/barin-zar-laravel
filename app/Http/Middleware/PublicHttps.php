<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        $publicHttps = app()->isProduction() || parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https';

        if ($publicHttps) {
            URL::forceHttps();
            config(['session.secure' => true]);
        }

        $response = $next($request);

        // Laravel can redirect to a full HTTP URL saved in a session before TLS was recognized.
        if ($response instanceof RedirectResponse && ($publicHttps || $request->isSecure())) {
            $location = $response->getTargetUrl();
            $parts = parse_url($location);
            if (($parts['scheme'] ?? null) === 'http' && strcasecmp($parts['host'] ?? '', $request->getHost()) === 0) {
                $response->setTargetUrl((string) Uri::of($location)->withScheme('https'));
            }
        }

        return $response;
    }
}
