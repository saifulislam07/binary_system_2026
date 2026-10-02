import { usePage } from '@inertiajs/vue3';
import bn from '../../../lang/bn.json';

/**
 * Member-facing translations. Keys are the English text (Laravel's JSON
 * translation style), so `lang/bn.json` serves both this helper and the
 * server's `__()`. English shows the key itself; a missing Bangla entry
 * falls back to English (Tests\Unit\TranslationCoverageTest prevents that).
 */
export type Locale = 'bn' | 'en';

const dictionaries: Record<Locale, Record<string, string>> = {
    bn: bn as Record<string, string>,
    en: {},
};

export function currentLocale(): Locale {
    return usePage().props.locale === 'en' ? 'en' : 'bn';
}

/** Translate `key`, filling `:name` placeholders like Laravel's __(). */
export function t(
    key: string,
    replace: Record<string, string | number> = {},
): string {
    let text = dictionaries[currentLocale()][key] ?? key;

    // Longest names first, so `:total` isn't eaten by `:to`.
    const names = Object.keys(replace).sort((a, b) => b.length - a.length);

    for (const name of names) {
        text = text.replaceAll(`:${name}`, String(replace[name]));
    }

    return text;
}

/** `t()` for counts: picks the `one` or `other` key by `count`. */
export function tc(
    one: string,
    other: string,
    count: number,
    replace: Record<string, string | number> = {},
): string {
    return t(count === 1 ? one : other, { count, ...replace });
}

/**
 * Text stored as "English · বাংলা" (older notifications): the half for the
 * current language. Anything else is returned unchanged.
 */
export function pickLanguage(text: string): string {
    const match = /^(.+?) · ([^·]*[ঀ-৿][^·]*)$/.exec(text);

    if (!match) {
        return text;
    }

    return currentLocale() === 'bn' ? match[2] : match[1];
}
