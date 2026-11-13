<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A business that is suspended, or whose trial or subscription ended, sees one page that says so. */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user()?->tenant;

        if ($tenant && ! $tenant->isUsable()) {
            return response()->view('errors.suspended', ['tenant' => $tenant], 402);
        }

        return $next($request);
    }
}
