<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = config('app.locale');

        if ($request->hasSession() && $request->session()->has('locale')) {
            $locale = $request->session()->get('locale');
        } elseif ($preferred = $request->getPreferredLanguage(['en', 'ru'])) {
            $locale = $preferred;
        }

        if (! in_array($locale, ['en', 'ru'], true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
