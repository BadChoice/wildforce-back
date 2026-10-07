<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->language;

        if (is_string($locale) && in_array($locale, array_keys(config('translations.locales')), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
