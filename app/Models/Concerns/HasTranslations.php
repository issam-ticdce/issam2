<?php

namespace App\Models\Concerns;

/**
 * Champs traduits stockés en JSON : {"fr": "...", "ar": "...", "en": "..."}.
 */
trait HasTranslations
{
    /**
     * Renvoie le texte dans la langue demandée, sinon dans la première langue remplie.
     */
    public function tr(string $field, ?string $locale = null): ?string
    {
        $values = $this->{$field};

        if (! is_array($values)) {
            return $values;
        }

        $locale ??= app()->getLocale();

        foreach (array_unique([$locale, ...config('ticdce.locales')]) as $candidate) {
            if (filled($values[$candidate] ?? null)) {
                return $values[$candidate];
            }
        }

        return null;
    }
}
