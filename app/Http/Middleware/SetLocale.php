<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Locale');

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $accept = $request->header('Accept-Language');
            $locale = $accept
                ? substr($accept, 0, 2)
                : config('app.locale');
        }

        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
