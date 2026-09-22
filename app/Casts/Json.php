<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Comme le cast "array" de Laravel, mais conserve l'arabe lisible dans la base
 * (sans \uXXXX), ce qui permet la recherche par LIKE dans les trois langues.
 */
class Json implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        return $value === null ? null : json_decode($value, true);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
