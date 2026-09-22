<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** Applique la langue indiquée dans l'URL (/fr, /ar, /en). */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (! in_array($locale, config('ticdce.locales'), true)) {
            abort(404);
        }

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        // Le paramètre de langue ne doit pas être passé aux contrôleurs.
        $request->route()->forgetParameter('locale');

        return $next($request);
    }
}
