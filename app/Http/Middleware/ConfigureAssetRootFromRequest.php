<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ConfigureAssetRootFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningInConsole()) {
            return $next($request);
        }

        $schemeHost = $request->getSchemeAndHttpHost();

        Config::set('app.asset_url', $schemeHost);

        URL::forceRootUrl($schemeHost);

        if ($request->isSecure()) {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
