<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasAppAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->hasAppAccess()) {
            return response()->json([
                'message' => 'An active subscription or active coach relationship is required.',
                'code' => 'subscription_required',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
