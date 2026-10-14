const amount = new Intl.NumberFormat('en-MY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** Money is stored as integer sen; display as "RM 1,234.50" (the PDF and texts use the same form). */
export function formatMoney(cents: number): string {
    return `${cents < 0 ? '−' : ''}RM ${amount.format(Math.abs(cents) / 100)}`;
}

/** Malaysian regional formats per interface language; follows <html lang>, which the language switcher updates. */
const INTL_LOCALES: Record<string, string> = {
    en: 'en-MY',
    ms: 'ms-MY',
    zh: 'zh-Hans-MY',
};

const intlLocale = (): string =>
    INTL_LOCALES[document.documentElement.lang] ?? 'en-MY';

export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(intlLocale(), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(intlLocale(), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

/** Amount typed in ringgit (e.g. "1,250.50") → sen, for live totals in forms. */
export function toCents(ringgit: string): number {
    const value = Number(ringgit.replace(/,/g, ''));

    return Number.isFinite(value) ? Math.round(value * 100) : 0;
}

/** Stored values → English keys for t(). */
export const PAYMENT_METHOD_LABELS: Record<string, string> = {
    transfer: 'Bank transfer',
    cash: 'Cash',
    cheque: 'Cheque',
    other: 'Other',
};

export const ACTOR_LABELS: Record<string, string> = {
    client: 'Client',
    contractor: 'Contractor',
    system: 'System',
};

/** Stored with 7 decimals; 5 is about a metre, plenty for a site. */
export function formatCoords(lat: string, lng: string): string {
    return `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`;
}
