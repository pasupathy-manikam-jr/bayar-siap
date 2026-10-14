import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { useTranslation } from '@/hooks/use-translation';

/** Error pages (rendered from the exception handler) in the site-office look. */
export default function ErrorPage({ status }: { status: number }) {
    const { t } = useTranslation();

    const messages: Record<number, { title: string; body: string }> = {
        403: {
            title: t('You don’t have access to this page'),
            body: t(
                'It belongs to another account. Log in with the account that owns it.',
            ),
        },
        404: {
            title: t('This page isn’t here'),
            body: t(
                'The link may be old or mistyped. If a contractor sent it, ask them to send it again.',
            ),
        },
        419: {
            title: t('This page expired'),
            body: t(
                'You were away for a while. Go back, refresh the page and try again.',
            ),
        },
        500: {
            title: t('Something went wrong on our side'),
            body: t(
                'Your work is saved up to the last step. Try again in a minute.',
            ),
        },
        503: {
            title: t('Down for maintenance'),
            body: t(
                'We’re updating JobProof. Please check back in a few minutes.',
            ),
        },
    };
    const message = messages[status] ?? messages[500];

    return (
        <>
            <Head title={message.title} />
            <div className="flex min-h-dvh flex-col bg-sidebar p-6 text-sidebar-foreground sm:p-12">
                <Link
                    href={home()}
                    className="flex items-center gap-2 self-start font-display text-xl font-semibold text-sidebar-accent-foreground"
                >
                    <span className="flex size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                        <AppLogoIcon className="size-5" />
                    </span>
                    JobProof
                </Link>
                <main className="flex flex-1 flex-col justify-center gap-6 py-16">
                    <p className="font-display text-8xl leading-none font-bold text-sidebar-primary tabular-nums sm:text-9xl">
                        {status}
                    </p>
                    <h1 className="max-w-xl text-4xl font-bold text-sidebar-accent-foreground sm:text-5xl">
                        {message.title}
                    </h1>
                    <p className="max-w-xl text-lg">{message.body}</p>
                    <Button size="lg" className="self-start" asChild>
                        <Link href={home()}>{t('Go to the home page')}</Link>
                    </Button>
                </main>
            </div>
        </>
    );
}
