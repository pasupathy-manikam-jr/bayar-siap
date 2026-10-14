import { usePage } from '@inertiajs/react';

/**
 * Interface translation. Keys are the English text, so an untranslated string simply shows in English.
 * Placeholders use Laravel's syntax: t('Due :date', { date: '12 Oct' }).
 */
export function useTranslation() {
    // Error pages (rendered outside the web middleware) get these passed in by the exception handler.
    const { translations, locale } = usePage().props;

    // Match whole placeholder names, so :to never eats the start of :total.
    const t = (key: string, replace: Record<string, string | number> = {}) =>
        (translations[key] ?? key).replace(/:([A-Za-z_]+)/g, (match, name) =>
            name in replace ? String(replace[name]) : match,
        );

    return { t, locale };
}
