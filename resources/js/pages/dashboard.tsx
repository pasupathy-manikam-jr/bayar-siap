import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import MoneyProgress from '@/components/money-progress';
import PhotoStrip from '@/components/photo-strip';
import type { StripPhoto } from '@/components/photo-strip';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime, formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/jobs';
import type { JobSummary } from '@/types/client';

type Props = {
    stats: {
        active_jobs: number;
        awaiting_acceptance: number;
        outstanding_cents: number;
        awaiting_client: number;
    };
    jobs: {
        id: number;
        title: string;
        client: string;
        status: string;
        summary: JobSummary;
    }[];
    photos: StripPhoto[];
    clientActivity: {
        id: number;
        action: string;
        label: string;
        job_id: number | null;
        job_title: string | null;
        meta: Record<string, string> | null;
        created_at: string | null;
    }[];
};

export default function Dashboard({
    stats,
    jobs,
    photos,
    clientActivity,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">{t('Dashboard')}</h1>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            {t('New job')}
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat label={t('Active jobs')} value={stats.active_jobs} />
                    <Stat
                        label={t('Waiting for client acceptance')}
                        value={stats.awaiting_acceptance}
                    />
                    <Stat
                        label={t('Waiting on client action')}
                        value={stats.awaiting_client}
                        hint={t(
                            'Payment requests, variation orders and payments to confirm',
                        )}
                    />
                    <Stat
                        label={t('Outstanding on active jobs')}
                        value={formatMoney(stats.outstanding_cents)}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Latest proof photos')}</CardTitle>
                        <CardDescription>
                            {t(
                                'From all your jobs. Click a photo to open its milestone.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PhotoStrip photos={photos} show="job" />
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>{t('Open jobs')}</CardTitle>
                            <Button
                                variant="link"
                                className="h-auto p-0"
                                asChild
                            >
                                <Link href={index()}>{t('All jobs')}</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {jobs.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No open jobs.')}{' '}
                                    <Link
                                        href={create()}
                                        className="text-foreground underline"
                                    >
                                        {t('Create your first job')}
                                    </Link>
                                    .
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Job')}</TableHead>
                                            <TableHead>{t('Status')}</TableHead>
                                            <TableHead>{t('Paid')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {jobs.map((job) => (
                                            <TableRow key={job.id}>
                                                <TableCell>
                                                    <Link
                                                        href={show(job.id)}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {job.title}
                                                    </Link>
                                                    <p className="text-xs text-muted-foreground">
                                                        {job.client}
                                                    </p>
                                                </TableCell>
                                                <TableCell>
                                                    <StatusBadge
                                                        status={job.status}
                                                        audience="contractor"
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <MoneyProgress
                                                        summary={job.summary}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Client activity')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'Approvals, rejections, signatures and confirmations',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {clientActivity.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('Nothing yet.')}
                                </p>
                            ) : (
                                <ul className="space-y-3">
                                    {clientActivity.map((entry) => (
                                        <li key={entry.id} className="text-sm">
                                            <p className="font-medium">
                                                {entry.label}
                                            </p>
                                            {entry.meta?.reason && (
                                                <p className="text-destructive">
                                                    “{entry.meta.reason}”
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                {entry.job_id ? (
                                                    <Link
                                                        href={show(
                                                            entry.job_id,
                                                        )}
                                                        className="hover:underline"
                                                    >
                                                        {entry.job_title}
                                                    </Link>
                                                ) : null}{' '}
                                                ·{' '}
                                                {formatDateTime(
                                                    entry.created_at,
                                                )}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    hint,
}: {
    label: string;
    value: string | number;
    hint?: string;
}) {
    return (
        <Card className="gap-1 py-4">
            <CardContent className="px-4">
                <p className="text-sm text-muted-foreground" title={hint}>
                    {label}
                </p>
                <p className="mt-1 font-display text-3xl font-semibold tabular-nums">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
