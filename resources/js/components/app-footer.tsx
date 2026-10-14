import { Link } from '@inertiajs/react';
import { LanguageSwitcher } from '@/components/language-switcher';
import { useTranslation } from '@/hooks/use-translation';
import { guide } from '@/routes';

/** Stays at the bottom of the logged-in pages: the language switcher and the guide, always in reach. */
export function AppFooter() {
    const { t } = useTranslation();

    return (
        <footer className="sticky bottom-0 z-20 mt-auto flex h-12 shrink-0 items-center justify-between gap-4 border-t bg-background px-4 text-sm text-muted-foreground">
            <span className="truncate">
                JobProof ·{' '}
                <Link
                    href={guide()}
                    className="text-foreground underline decoration-primary decoration-2 underline-offset-4"
                >
                    {t('User guide')}
                </Link>
            </span>
            <LanguageSwitcher />
        </footer>
    );
}
