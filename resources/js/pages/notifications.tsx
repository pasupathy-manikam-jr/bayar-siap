import { Head, Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { notifications as notificationsRoute } from '@/routes';

type Props = {
    notifications: {
        id: string;
        title: string;
        body: string;
        url: string;
        unread: boolean;
        created_at: string | null;
    }[];
};

export default function Notifications({ notifications }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Notifications')} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">{t('Notifications')}</h1>
                <Card className="py-0">
                    <CardContent className="p-0">
                        {notifications.length === 0 ? (
                            <p className="flex items-center gap-2 p-6 text-sm text-muted-foreground">
                                <Bell className="size-4" />
                                {t(
                                    'Approvals, rejections, signatures and confirmations from clients appear here.',
                                )}
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {notifications.map((n) => (
                                    <li key={n.id}>
                                        <Link
                                            href={n.url}
                                            className={cn(
                                                'flex gap-3 p-4 hover:bg-muted/50',
                                                n.unread && 'bg-accent',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'mt-1.5 size-2 shrink-0 rounded-full',
                                                    n.unread
                                                        ? 'bg-primary'
                                                        : 'bg-transparent',
                                                )}
                                                aria-hidden
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-medium">
                                                    {n.title}
                                                    {n.unread && (
                                                        <span className="sr-only">
                                                            {' '}
                                                            {t('(new)')}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {n.body}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        n.created_at,
                                                    )}
                                                </span>
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [{ title: 'Notifications', href: notificationsRoute() }],
};
