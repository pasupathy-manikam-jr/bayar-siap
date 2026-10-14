import { router, usePage } from '@inertiajs/react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { update } from '@/routes/locale';
import { locale as clientLocale } from '@/routes/client';

/**
 * EN / BM / 中文 switcher. On a client link it also saves the choice on the client, so their texts follow it.
 */
export function LanguageSwitcher({
    shareToken,
    className,
}: {
    shareToken?: string;
    className?: string;
}) {
    const { locales } = usePage().props;
    const { t, locale } = useTranslation();

    const switchTo = (code: string) => {
        if (!code || code === locale) {
            return;
        }

        router.post(
            shareToken ? clientLocale.url(shareToken) : update.url(),
            { locale: code },
            {
                preserveScroll: true,
                onSuccess: () => {
                    document.documentElement.lang = code;
                },
            },
        );
    };

    return (
        <ToggleGroup
            type="single"
            size="sm"
            variant="outline"
            value={locale}
            onValueChange={switchTo}
            aria-label={t('Language')}
            className={cn('w-fit', className)}
        >
            {Object.entries(locales).map(([code, label]) => (
                <ToggleGroupItem
                    key={code}
                    value={code}
                    lang={code}
                    className="px-2.5"
                >
                    {label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
