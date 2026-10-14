import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LanguageSwitcher } from '@/components/language-switcher';
import StatusBadge from '@/components/status-badge';
import TapeProgress from '@/components/tape-progress';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { dashboard, login, register } from '@/routes';
import { useTranslation } from '@/hooks/use-translation';

export default function Welcome() {
    const { auth, name } = usePage().props;
    const { t } = useTranslation();

    const sampleMilestones = [
        { name: t('Deposit'), amount: 'RM 14,550', status: 'paid' },
        {
            name: t('Demolition and rough works'),
            amount: 'RM 14,550',
            status: 'requested',
        },
        { name: t('Fit-out'), amount: 'RM 14,550', status: 'planned' },
        { name: t('Handover'), amount: 'RM 4,850', status: 'planned' },
    ];

    const steps = [
        {
            title: t('Split the quote'),
            body: t(
                'Break the job into milestones that add up to the quote. Templates for house builds, renovations and installations.',
            ),
        },
        {
            title: t('Prove the work'),
            body: t(
                'Photos keep the time and place they were taken, and each file is fingerprinted so changes show.',
            ),
        },
        {
            title: t('Client approves'),
            body: t(
                'Your client opens a link on their phone, sees the proof and approves, or rejects with a reason.',
            ),
        },
        {
            title: t('Payment confirmed'),
            body: t(
                'You record the money, the client confirms it, and both of you see the same balance.',
            ),
        },
    ];

    return (
        <>
            <Head title={t('Paid milestones with proof')} />
            <div className="min-h-screen bg-background text-foreground">
                <header className="bg-sidebar text-sidebar-foreground">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                        <div className="flex items-center gap-2 font-display text-xl font-semibold text-sidebar-accent-foreground">
                            <span className="flex size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                <AppLogoIcon className="size-5" />
                            </span>
                            {name}
                        </div>
                        <nav className="flex items-center gap-2">
                            <LanguageSwitcher className="[&_button]:border-sidebar-border [&_button]:text-sidebar-foreground [&_button[data-state=on]]:bg-sidebar-primary [&_button[data-state=on]]:text-sidebar-primary-foreground" />
                            {auth.user ? (
                                <Button asChild>
                                    <Link href={dashboard()}>
                                        {t('Open dashboard')}
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        variant="ghost"
                                        className="text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                                        asChild
                                    >
                                        <Link href={login()}>
                                            {t('Log in')}
                                        </Link>
                                    </Button>
                                    <Button asChild>
                                        <Link href={register()}>
                                            {t('Register')}
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="mx-auto grid max-w-6xl grid-cols-[minmax(0,1fr)] items-center gap-12 px-4 py-14 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:py-20">
                        <div className="flex flex-col gap-6">
                            <h1 className="text-5xl leading-[0.95] font-bold sm:text-6xl lg:text-7xl">
                                {t('Split the job.')}
                                <br />
                                {t('Prove the work.')}
                                <br />
                                {t('Get paid on record.')}
                            </h1>
                            <p className="max-w-xl text-lg text-muted-foreground">
                                {t(
                                    'Milestones, photo proof and your client’s approval for every payment, so scope, progress and money stop living in WhatsApp chats.',
                                )}
                            </p>
                            {!auth.user && (
                                <div className="flex flex-wrap gap-3">
                                    <Button size="lg" asChild>
                                        <Link href={register()}>
                                            {t('Start free with one job')}
                                        </Link>
                                    </Button>
                                    <Button size="lg" variant="outline" asChild>
                                        <Link href={login()}>
                                            {t('Log in')}
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </div>

                        <Card aria-label={t('Example job record')}>
                            <CardHeader>
                                <CardDescription>
                                    12 Jalan Mawar 3, Shah Alam
                                </CardDescription>
                                <CardTitle className="font-display text-2xl">
                                    {t('Kitchen renovation')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-5">
                                <div className="flex flex-col gap-2">
                                    <div className="flex items-baseline justify-between gap-4">
                                        <span className="font-display text-3xl font-semibold tabular-nums">
                                            RM 14,550
                                        </span>
                                        <span className="text-sm text-muted-foreground tabular-nums">
                                            {t('paid of :amount', {
                                                amount: 'RM 48,500',
                                            })}
                                        </span>
                                    </div>
                                    <TapeProgress
                                        percent={30}
                                        label={t('Paid so far')}
                                    />
                                </div>
                                <ol className="flex flex-col">
                                    {sampleMilestones.map((m, i) => (
                                        <li key={m.name}>
                                            {i > 0 && <Separator />}
                                            <div className="flex items-center gap-3 py-2.5">
                                                <span className="w-4 text-sm text-muted-foreground tabular-nums">
                                                    {i + 1}
                                                </span>
                                                <span className="min-w-0 flex-1 truncate">
                                                    {m.name}
                                                </span>
                                                <span className="text-sm tabular-nums">
                                                    {m.amount}
                                                </span>
                                                <StatusBadge
                                                    status={m.status}
                                                    audience="contractor"
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'VO-001 pantry cabinet, +RM 2,800, signed by the client on 3 Oct.',
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    </section>

                    <section className="border-y bg-card">
                        <div className="mx-auto max-w-6xl px-4 py-14">
                            <h2 className="text-3xl font-bold">
                                {t('How a milestone gets paid')}
                            </h2>
                            <ol className="mt-8 grid gap-8 md:grid-cols-4 md:gap-6">
                                {steps.map((step, i) => (
                                    <li
                                        key={step.title}
                                        className="flex flex-col gap-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary font-display text-lg font-bold text-primary-foreground">
                                                {i + 1}
                                            </span>
                                            {i < steps.length - 1 && (
                                                <Separator className="hidden flex-1 md:block" />
                                            )}
                                        </div>
                                        <h3 className="font-display text-xl font-semibold">
                                            {step.title}
                                        </h3>
                                        <p className="text-muted-foreground">
                                            {step.body}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <p className="mx-auto max-w-6xl px-4 py-10 text-muted-foreground">
                        {t(
                            'Got a link from your contractor? Open it on your phone; you don’t need an account.',
                        )}
                    </p>
                </main>
            </div>
        </>
    );
}
