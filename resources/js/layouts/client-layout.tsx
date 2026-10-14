import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useTranslation } from '@/hooks/use-translation';

/** The client link: no account, no sidebar — just the job, on a phone. */
export default function ClientLayout({ children }: { children: ReactNode }) {
    const { t } = useTranslation();

    return (
        <div className="min-h-svh bg-muted/40">
            <main className="mx-auto w-full max-w-2xl px-4 py-6 sm:py-10">
                {children}
            </main>
            <footer className="flex items-center justify-center gap-1.5 pb-8 text-xs text-muted-foreground">
                <AppLogoIcon className="size-3.5 text-foreground" />
                {t('Job record kept by JobProof')}
            </footer>
        </div>
    );
}
