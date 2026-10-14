import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import MoneyProgress from '@/components/money-progress';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/jobs';
import type { JobSummary } from '@/types/client';

type JobRow = {
    id: number;
    title: string;
    client: string;
    site_address: string;
    status: string;
    summary: JobSummary;
    next_milestone: {
        name: string;
        due_date: string | null;
        status: string;
    } | null;
    updated_at: string | null;
};

type Props = {
    jobs: {
        data: JobRow[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { q?: string; status?: string };
};

const statuses = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent to client' },
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

export default function JobsIndex({ jobs, filters }: Props) {
    const { t } = useTranslation();
    const [q, setQ] = useState(filters.q ?? '');

    const apply = (next: { q?: string; status?: string }) => {
        const query = { q, status: filters.status, ...next };
        router.get(
            index.url({
                query: {
                    q: query.q || undefined,
                    status:
                        query.status && query.status !== 'all'
                            ? query.status
                            : undefined,
                },
            }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={t('Jobs')} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">{t('Jobs')}</h1>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            {t('New job')}
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row">
                    <form
                        noValidate
                        className="relative flex-1"
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply({ q });
                        }}
                    >
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            onBlur={() =>
                                q !== (filters.q ?? '') && apply({ q })
                            }
                            placeholder={t('Search job, address or client')}
                            className="pl-9"
                            aria-label={t('Search jobs')}
                        />
                    </form>
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(status) => apply({ status })}
                    >
                        <SelectTrigger
                            className="sm:w-48"
                            aria-label={t('Status')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {statuses.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {t(s.label)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Job')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    {t('Next milestone')}
                                </TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    {t('Paid')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {jobs.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        {t('No jobs found.')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {jobs.data.map((job) => (
                                <TableRow key={job.id}>
                                    <TableCell className="max-w-72">
                                        <Link
                                            href={show(job.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {job.title}
                                        </Link>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {job.client} · {job.site_address}
                                        </p>
                                        <div className="mt-2 sm:hidden">
                                            <MoneyProgress
                                                summary={job.summary}
                                            />
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={job.status}
                                            audience="contractor"
                                        />
                                    </TableCell>
                                    <TableCell className="hidden lg:table-cell">
                                        {job.next_milestone ? (
                                            <>
                                                <p className="text-sm">
                                                    {job.next_milestone.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Due :date', {
                                                        date: formatDate(
                                                            job.next_milestone
                                                                .due_date,
                                                        ),
                                                    })}
                                                </p>
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="hidden sm:table-cell">
                                        <MoneyProgress summary={job.summary} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {jobs.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            {t('Page :current of :last · :total jobs', {
                                current: jobs.current_page,
                                last: jobs.last_page,
                                total: jobs.total,
                            })}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!jobs.prev_page_url}
                                asChild={!!jobs.prev_page_url}
                            >
                                {jobs.prev_page_url ? (
                                    <Link
                                        href={jobs.prev_page_url}
                                        preserveState
                                    >
                                        {t('Previous')}
                                    </Link>
                                ) : (
                                    t('Previous')
                                )}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!jobs.next_page_url}
                                asChild={!!jobs.next_page_url}
                            >
                                {jobs.next_page_url ? (
                                    <Link
                                        href={jobs.next_page_url}
                                        preserveState
                                    >
                                        {t('Next')}
                                    </Link>
                                ) : (
                                    t('Next')
                                )}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

JobsIndex.layout = {
    breadcrumbs: [{ title: 'Jobs', href: index() }],
};
