<?php

namespace App\Filament\Shared;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Champ traduit : un onglet par langue (FR / AR / EN).
 * Au moins une langue doit être remplie si $required est vrai.
 */
class Translatable
{
    /**
     * @param  Closure(string $statePath): Field  $field
     */
    public static function make(string $name, string $label, Closure $field, bool $required = false): Fieldset
    {
        $locales = config('ticdce.locales');

        $tabs = Tabs::make($name)
            ->contained(false)
            ->tabs(collect($locales)->map(function (string $locale) use ($name, $label, $field, $required, $locales) {
                $others = array_diff($locales, [$locale]);

                return Tab::make(config('ticdce.locale_names')[$locale])
                    ->schema([
                        $field("{$name}.{$locale}")
                            ->label($label.' ('.strtoupper($locale).')')
                            ->hiddenLabel()
                            ->extraInputAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale])
                            ->required(fn (Get $get) => $required && $locale === $locales[0]
                                && collect($others)->every(fn ($other) => blank($get("{$name}.{$other}")))),
                    ]);
            })->all());

        return Fieldset::make($label.($required ? ' *' : ''))->columns(1)->schema([$tabs]);
    }
}
