<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A 404 rather than a 403: there is no reason to confirm to a signed-in
        // non-admin that an admin panel exists at this path.
        if (! $user || ! $user->isAdmin()) {
            abort(404);
        }

        return $next($request);
    }
}
