<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasTenantAccess()) {
            abort(Response::HTTP_FORBIDDEN, 'Acesso restrito ao portal do locatário.');
        }

        return $next($request);
    }
}
