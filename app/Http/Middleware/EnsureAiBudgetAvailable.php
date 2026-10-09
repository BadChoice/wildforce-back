<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiBudgetAvailable
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isAiBlocked()) {
            return response()->json([
                'message' => 'AI features are disabled for this account.',
                'code' => 'ai_blocked',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->hasExceededMonthlyAiBudget()) {
            return response()->json([
                'message' => 'The monthly AI usage limit has been reached.',
                'code' => 'ai_budget_exceeded',
                'resets_at' => now()->startOfMonth()->addMonth()->toIso8601String(),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return $next($request);
    }
}
