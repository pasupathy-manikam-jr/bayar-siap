<?php

namespace App\Support;

/** Interface strings for one language from lang/<locale>.json (keys are the English text). */
class Translations
{
    /** @return list<string> language codes the app offers (config jobproof.locales) */
    public static function locales(): array
    {
        return array_map('strval', array_keys((array) config('jobproof.locales')));
    }

    /** @return array<string, string> */
    public static function for(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        return $locale !== 'en' && is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }
}
