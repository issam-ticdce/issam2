<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Espaces privés : langue choisie par l'utilisateur (profil), sinon ?lang=, sinon français. */
class SetPanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = config('ticdce.locales');

        if (in_array($request->query('lang'), $locales, true)) {
            $request->session()->put('panel_locale', $request->query('lang'));
        }

        $locale = $request->user()?->locale ?? $request->session()->get('panel_locale');

        app()->setLocale(in_array($locale, $locales, true) ? $locale : $locales[0]);

        return $next($request);
    }
}
