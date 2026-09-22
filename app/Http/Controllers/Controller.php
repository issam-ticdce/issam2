<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

abstract class Controller
{
    /** Les pages d'aperçu n'ont pas de langue dans l'URL : ?lang=ar, sinon la langue de l'utilisateur. */
    protected function usePreviewLocale(Request $request): void
    {
        $locale = $request->query('lang', $request->user()?->locale);
        $locale = in_array($locale, config('ticdce.locales'), true) ? $locale : config('ticdce.locales')[0];

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
    }
}
