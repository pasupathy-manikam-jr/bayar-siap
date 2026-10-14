import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LanguageSwitcher } from '@/components/language-switcher';
import TapeProgress from '@/components/tape-progress';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

/** Auth pages: the ink site-office panel on the left (a top bar on phones), the form on concrete. */
export default function AuthBrandLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;
    const { t } = useTranslation();

    return (
        <div className="grid min-h-dvh bg-background lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
            <aside className="flex flex-col gap-10 bg-sidebar p-6 text-sidebar-foreground lg:p-12">
                <div className="flex items-center justify-between gap-4">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-display text-xl font-semibold text-sidebar-accent-foreground"
                    >
                        <span className="flex size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                            <AppLogoIcon className="size-5" />
                        </span>
                        {name}
                    </Link>
                    <LanguageSwitcher className="[&_button]:border-sidebar-border [&_button]:text-sidebar-foreground [&_button[data-state=on]]:bg-sidebar-primary [&_button[data-state=on]]:text-sidebar-primary-foreground" />
                </div>
                <div className="hidden flex-1 flex-col justify-center gap-8 lg:flex">
                    <p className="font-display text-5xl leading-[0.95] font-bold text-sidebar-accent-foreground xl:text-6xl">
                        {t('Split the job.')}
                        <br />
                        {t('Prove the work.')}
                        <br />
                        {t('Get paid on record.')}
                    </p>
                    <div className="flex max-w-sm flex-col gap-2">
                        <TapeProgress
                            percent={60}
                            label={t('Example: 60% of a job paid')}
                            className="bg-sidebar-accent ring-sidebar-border"
                        />
                        <p className="text-sm">
                            {t(
                                'Every milestone proved with photos and approved by your client before it’s paid.',
                            )}
                        </p>
                    </div>
                </div>
            </aside>

            <main className="flex items-center justify-center p-6 sm:p-10">
                <div className="flex w-full max-w-sm flex-col gap-8">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-3xl font-bold">{t(title ?? '')}</h1>
                        <p className="text-muted-foreground">
                            {t(description ?? '')}
                        </p>
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
