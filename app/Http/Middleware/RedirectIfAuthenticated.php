<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = $guards === [] ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                $portalDomain = config('app.portal_domain');
                $host = $request->getHost();

                $targetRoute = 'dashboard';

                if ($user && method_exists($user, 'hasTenantAccess') && $user->hasTenantAccess()) {
                    $hasAdminAccess = method_exists($user, 'can') && $user->can('admin.access');
                    if (($portalDomain && $host === $portalDomain) || ! $hasAdminAccess) {
                        if (Route::has('portal.dashboard')) {
                            $targetRoute = 'portal.dashboard';
                        }
                    }
                }

                return redirect()->intended(route($targetRoute));
            }
        }

        return $next($request);
    }
}
