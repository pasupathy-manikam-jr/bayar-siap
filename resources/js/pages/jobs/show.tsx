import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Ban,
    Check,
    Copy,
    Download,
    MessageCircle,
    MessageSquare,
    Pencil,
    Plus,
    Send,
} from 'lucide-react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import ReasonDialog from '@/components/reason-dialog';
import CommentThread from '@/components/comment-thread';
import type { ThreadComment } from '@/components/comment-thread';
import DatePicker from '@/components/date-picker';
import FormDialog from '@/components/form-dialog';
import InputError from '@/components/input-error';
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
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    PAYMENT_METHOD_LABELS,
    ACTOR_LABELS,
} from '@/lib/format';
import { store as storeComment } from '@/routes/comments';
import { cancel, edit, index, record, send, show } from '@/routes/jobs';
import { store as storePayment } from '@/routes/jobs/payments';
import { store as storeVariationOrder } from '@/routes/jobs/variation-orders';
import { show as showMilestone, start } from '@/routes/milestones';
import { send as sendVariationOrder } from '@/routes/variation-orders';
import type { JobSummary } from '@/types/client';

type Milestone = {
    id: number;
    sequence: number;
    name: string;
    amount_cents: number;
    status: string;
    proofs_count: number;
    variation_order_id: number | null;
    due_date: string | null;
    open_request_id: number | null;
};

type VariationOrder = {
    id: number;
    number: string;
    description: string;
    reason: string | null;
    amount_cents: number;
    status: string;
    signer_name: string | null;
    reject_reason: string | null;
    signed_at: string | null;
    comments: ThreadComment[];
};

type Payment = {
    id: number;
    amount_cents: number;
    method: string;
    reference: string | null;
    client_status: string;
    dispute_note: string | null;
    paid_on: string;
    milestone: string | null;
};

type Props = {
    job: {
        id: number;
        title: string;
        site_address: string;
        status: string;
        start_date: string | null;
        target_date: string | null;
        accepted_at: string | null;
        client: {
            id: number;
            name: string;
            phone: string;
            email: string | null;
        };
        share_url: string | null;
        cancel_reason: string | null;
    };
    summary: JobSummary;
    milestones: Milestone[];
    variationOrders: VariationOrder[];
    payments: Payment[];
    photos: StripPhoto[];
    activity: {
        id: number;
        action: string;
        label: string;
        actor: 'client' | 'contractor' | 'system';
        meta: Record<string, string> | null;
        created_at: string | null;
    }[];
};

export default function JobShow({
    job,
    summary,
    milestones,
    variationOrders,
    payments,
    photos,
    activity,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const isLive = job.status === 'active';
    const canChangeMoney = job.status === 'sent' || job.status === 'active';

    return (
        <>
            <Head title={job.title} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold">
                                {job.title}
                            </h1>
                            <StatusBadge
                                status={job.status}
                                audience="contractor"
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {job.client.name} · {job.client.phone} ·{' '}
                            {job.site_address}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {job.accepted_at
                                ? t(':start → :target · accepted :accepted', {
                                      start: formatDate(job.start_date),
                                      target: formatDate(job.target_date),
                                      accepted: formatDateTime(job.accepted_at),
                                  })
                                : `${formatDate(job.start_date)} → ${formatDate(job.target_date)}`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {job.status === 'draft' && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={edit(job.id)}>
                                        <Pencil />
                                        {t('Edit')}
                                    </Link>
                                </Button>
                                <Form noValidate {...send.form(job.id)}>
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Send />
                                            )}
                                            {t('Send to client')}
                                        </Button>
                                    )}
                                </Form>
                            </>
                        )}
                        {job.share_url && (
                            <ShareLink
                                url={job.share_url}
                                phone={job.client.phone}
                                title={job.title}
                            />
                        )}
                        {['draft', 'sent', 'active'].includes(job.status) && (
                            <ReasonDialog
                                action={cancel.form(job.id)}
                                field="reason"
                                trigger={
                                    <Button
                                        variant="outline"
                                        className="text-destructive"
                                    >
                                        <Ban />
                                        {t('Cancel job')}
                                    </Button>
                                }
                                title={t('Cancel “:title”?', {
                                    title: job.title,
                                })}
                                description={t(
                                    'This can’t be undone. Open payment requests are withdrawn and the client is told. Payments already recorded stay on the record.',
                                )}
                                label={t('Reason (shown to the client)')}
                                submitLabel={t('Cancel job')}
                            />
                        )}
                        <Button variant="outline" asChild>
                            <a href={record.url(job.id)}>
                                <Download />
                                {t('PDF record')}
                            </a>
                        </Button>
                    </div>
                </div>

                {errors.rule && <AlertError errors={[errors.rule]} />}

                {job.status === 'cancelled' && (
                    <p className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm">
                        <span className="font-medium">
                            {t('This job was cancelled.')}
                        </span>
                        {job.cancel_reason &&
                            ` ${t('Reason: “:reason”.', { reason: job.cancel_reason })}`}
                    </p>
                )}

                {job.status === 'sent' && (
                    <p className="rounded-md border border-status-waiting/40 bg-status-waiting/10 p-3 text-sm">
                        {t(
                            'Waiting for :client to accept the job. Share the link if they haven’t opened it yet.',
                            { client: job.client.name },
                        )}
                    </p>
                )}

                <div className="grid gap-4 sm:grid-cols-4">
                    <Stat
                        label={t('Quote')}
                        value={formatMoney(summary.quote_cents)}
                    />
                    <Stat
                        label={t('Total incl. signed VOs')}
                        value={formatMoney(summary.total_cents)}
                    />
                    <Stat
                        label={t('Confirmed by client')}
                        value={formatMoney(summary.confirmed_cents)}
                    />
                    <Stat
                        label={t('Balance')}
                        value={formatMoney(summary.balance_cents)}
                    />
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle>{t('Milestones')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'Start a milestone, add photo proof, then request payment.',
                                )}
                            </CardDescription>
                        </div>
                        <MoneyProgress summary={summary} />
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">#</TableHead>
                                    <TableHead>{t('Milestone')}</TableHead>
                                    <TableHead>{t('Due')}</TableHead>
                                    <TableHead>{t('Proof')}</TableHead>
                                    <TableHead>{t('Status')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Amount')}
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {milestones.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell>{m.sequence}</TableCell>
                                        <TableCell className="whitespace-normal">
                                            <Link
                                                href={showMilestone(m.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {m.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(m.due_date)}
                                        </TableCell>
                                        <TableCell>{m.proofs_count}</TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={m.status}
                                                audience="contractor"
                                            />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(m.amount_cents)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {m.status === 'planned' &&
                                            isLive ? (
                                                <Form
                                                    noValidate
                                                    {...start.form(m.id)}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {t('Start')}
                                                        </Button>
                                                    )}
                                                </Form>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                >
                                                    <Link
                                                        href={showMilestone(
                                                            m.id,
                                                        )}
                                                    >
                                                        {t('Open')}
                                                    </Link>
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Latest proof photos')}</CardTitle>
                        <CardDescription>
                            {t('Click a photo to open its milestone.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PhotoStrip photos={photos} show="milestone" />
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div className="space-y-1.5">
                                <CardTitle>{t('Variation orders')}</CardTitle>
                                <CardDescription>
                                    {t(
                                        'Extra or removed work. Counts only once the client signs.',
                                    )}
                                </CardDescription>
                            </div>
                            {canChangeMoney && (
                                <NewVariationOrder
                                    jobId={job.id}
                                    milestones={milestones}
                                />
                            )}
                        </CardHeader>
                        <CardContent>
                            {variationOrders.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('None yet.')}
                                </p>
                            ) : (
                                <Table>
                                    <TableBody>
                                        {variationOrders.map((vo) => (
                                            <TableRow key={vo.id}>
                                                <TableCell className="align-top font-medium">
                                                    {vo.number}
                                                </TableCell>
                                                <TableCell className="whitespace-normal">
                                                    {vo.description}
                                                    {vo.reject_reason && (
                                                        <span className="block text-xs text-destructive">
                                                            {t(
                                                                'Rejected: :reason',
                                                                {
                                                                    reason: vo.reject_reason,
                                                                },
                                                            )}
                                                        </span>
                                                    )}
                                                    <VoComments vo={vo} />
                                                    {vo.signer_name && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            {t(
                                                                'Signed by :name, :date',
                                                                {
                                                                    name: vo.signer_name,
                                                                    date: formatDateTime(
                                                                        vo.signed_at,
                                                                    ),
                                                                },
                                                            )}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right align-top tabular-nums">
                                                    {vo.amount_cents < 0
                                                        ? '−'
                                                        : '+'}
                                                    {formatMoney(
                                                        Math.abs(
                                                            vo.amount_cents,
                                                        ),
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right align-top">
                                                    {vo.status === 'draft' ? (
                                                        <Form
                                                            noValidate
                                                            {...sendVariationOrder.form(
                                                                vo.id,
                                                            )}
                                                        >
                                                            {({
                                                                processing,
                                                            }) => (
                                                                <Button
                                                                    size="sm"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    {t('Send')}
                                                                </Button>
                                                            )}
                                                        </Form>
                                                    ) : (
                                                        <StatusBadge
                                                            status={vo.status}
                                                            audience="contractor"
                                                        />
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div className="space-y-1.5">
                                <CardTitle>{t('Payments')}</CardTitle>
                                <CardDescription>
                                    {t(
                                        'Money received. The client confirms each one.',
                                    )}
                                </CardDescription>
                            </div>
                            {isLive && (
                                <RecordPayment
                                    jobId={job.id}
                                    milestones={milestones}
                                />
                            )}
                        </CardHeader>
                        <CardContent>
                            {payments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('None recorded.')}
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Date')}</TableHead>
                                            <TableHead>
                                                {t('Details')}
                                            </TableHead>
                                            <TableHead className="text-right">
                                                {t('Amount')}
                                            </TableHead>
                                            <TableHead className="text-right">
                                                {t('Client')}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payments.map((p) => (
                                            <TableRow key={p.id}>
                                                <TableCell className="align-top">
                                                    {formatDate(p.paid_on)}
                                                </TableCell>
                                                <TableCell className="whitespace-normal">
                                                    {t(
                                                        PAYMENT_METHOD_LABELS[
                                                            p.method
                                                        ] ?? p.method,
                                                    )}
                                                    {p.reference &&
                                                        ` · ${p.reference}`}
                                                    {p.milestone && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            {p.milestone}
                                                        </span>
                                                    )}
                                                    {p.dispute_note && (
                                                        <span className="block text-xs text-destructive">
                                                            {t(
                                                                'Disputed: :note',
                                                                {
                                                                    note: p.dispute_note,
                                                                },
                                                            )}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right align-top tabular-nums">
                                                    {formatMoney(
                                                        p.amount_cents,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right align-top">
                                                    <StatusBadge
                                                        status={p.client_status}
                                                        audience="contractor"
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <ActivityFeed activity={activity} />
            </div>
        </>
    );
}

const PROBLEMS = [
    'payment_request.rejected',
    'variation_order.rejected',
    'payment.disputed',
    'job.cancelled',
];

function ActivityFeed({ activity }: { activity: Props['activity'] }) {
    const { t } = useTranslation();
    const [showAll, setShowAll] = useState(false);
    const shown = showAll ? activity : activity.slice(0, 8);

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Activity')}</CardTitle>
                <CardDescription>
                    {t(
                        'Every action on this job, newest first. It can’t be edited.',
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <ol className="flex flex-col gap-3 border-l-2 border-primary pl-4">
                    {shown.map((entry) => {
                        const quote = entry.meta?.reason ?? entry.meta?.note;

                        return (
                            <li key={entry.id} className="text-sm">
                                <span className="font-medium">
                                    {entry.label}
                                </span>
                                {quote && (
                                    <span
                                        className={cn(
                                            PROBLEMS.includes(entry.action)
                                                ? 'text-destructive'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {' '}
                                        — “{quote}”
                                    </span>
                                )}
                                <span className="block text-xs text-muted-foreground">
                                    {t(
                                        ACTOR_LABELS[entry.actor] ??
                                            entry.actor,
                                    )}{' '}
                                    · {formatDateTime(entry.created_at)}
                                </span>
                            </li>
                        );
                    })}
                </ol>
                {activity.length > 8 && (
                    <Button
                        variant="link"
                        className="h-auto self-start p-0"
                        onClick={() => setShowAll(!showAll)}
                    >
                        {showAll
                            ? t('Show fewer')
                            : t('Show all :count', { count: activity.length })}
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <Card className="gap-1 py-4">
            <CardContent className="px-4">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p className="mt-1 font-display text-2xl font-semibold tabular-nums">
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function ShareLink({
    url,
    phone,
    title,
}: {
    url: string;
    phone: string;
    title: string;
}) {
    const { t } = useTranslation();
    const [copied, copy] = useClipboard();
    // Malaysian numbers are usually typed as 01x…; wa.me needs the country code.
    const digits = phone.replace(/\D/g, '').replace(/^0/, '60');
    const message = t(
        'Hi, here is the progress record for ":title". Open it to review and approve: :url',
        { title, url },
    );

    return (
        <>
            <Button variant="outline" onClick={() => copy(url)}>
                {copied ? <Check /> : <Copy />}
                {copied ? t('Copied') : t('Copy client link')}
            </Button>
            <Button variant="outline" asChild>
                <a
                    href={`https://wa.me/${digits}?text=${encodeURIComponent(message)}`}
                    target="_blank"
                    rel="noreferrer"
                >
                    <MessageCircle />
                    WhatsApp
                </a>
            </Button>
        </>
    );
}

function VoComments({ vo }: { vo: VariationOrder }) {
    const { t } = useTranslation();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="link"
                    size="sm"
                    className="block h-auto p-0 text-xs"
                >
                    <MessageSquare className="mr-1 inline size-3" />
                    {vo.comments.length === 0
                        ? t('Comment')
                        : vo.comments.length === 1
                          ? t('1 comment')
                          : t(':count comments', {
                                count: vo.comments.length,
                            })}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {vo.number}: {vo.description}
                    </DialogTitle>
                </DialogHeader>
                <CommentThread
                    comments={vo.comments}
                    action={storeComment.form()}
                    commentable={{ type: 'variation_order', id: vo.id }}
                    viewer="contractor"
                />
            </DialogContent>
        </Dialog>
    );
}

function MilestoneSelect({
    milestones,
    name,
    optional,
    placeholder,
}: {
    milestones: Milestone[];
    name: string;
    optional?: boolean;
    placeholder: string;
}) {
    const { t } = useTranslation();
    const NONE = 'none';
    const [value, setValue] = useState(optional ? NONE : '');

    return (
        <>
            <Select value={value} onValueChange={setValue}>
                <SelectTrigger id={name} className="w-full">
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {optional && (
                        <SelectItem value={NONE}>
                            {t('Not linked to a milestone')}
                        </SelectItem>
                    )}
                    {milestones.map((m) => (
                        <SelectItem key={m.id} value={String(m.id)}>
                            {m.sequence}. {m.name} (
                            {formatMoney(m.amount_cents)})
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <input
                type="hidden"
                name={name}
                value={value === NONE ? '' : value}
            />
        </>
    );
}

function NewVariationOrder({
    jobId,
    milestones,
}: {
    jobId: number;
    milestones: Milestone[];
}) {
    const { t } = useTranslation();
    const [kind, setKind] = useState<'extra' | 'deduction'>('extra');
    const adjustable = milestones.filter(
        (m) => m.status === 'planned' || m.status === 'in_progress',
    );

    return (
        <FormDialog
            action={storeVariationOrder.form(jobId)}
            trigger={
                <Button size="sm" variant="outline">
                    <Plus />
                    {t('New VO')}
                </Button>
            }
            title={t('New variation order')}
            description={t(
                'Saved as a draft. Send it when you’re ready for the client to sign.',
            )}
            submitLabel={t('Save draft')}
        >
            {(errors) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="kind">{t('Type')}</Label>
                        <Select
                            value={kind}
                            onValueChange={(v) => setKind(v as typeof kind)}
                        >
                            <SelectTrigger id="kind" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="extra">
                                    {t('Extra work (adds to total)')}
                                </SelectItem>
                                <SelectItem value="deduction">
                                    {t('Removed work (deducts)')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input type="hidden" name="kind" value={kind} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="vo_description">
                            {t('Description')}
                        </Label>
                        <Textarea
                            id="vo_description"
                            name="description"
                            rows={3}
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="vo_amount">{t('Amount (RM)')}</Label>
                        <Input
                            id="vo_amount"
                            name="amount"
                            inputMode="decimal"
                            placeholder="2800.00"
                        />
                        <InputError message={errors.amount} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="milestone_id">
                            {kind === 'deduction'
                                ? t('Deduct from milestone')
                                : t('Add to milestone (optional)')}
                        </Label>
                        <MilestoneSelect
                            key={kind}
                            milestones={adjustable}
                            name="milestone_id"
                            optional={kind === 'extra'}
                            placeholder={t('Choose a milestone')}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Extra work without a milestone becomes a new milestone when signed.',
                            )}
                        </p>
                        <InputError message={errors.milestone_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="vo_reason">
                            {t('Reason (optional)')}
                        </Label>
                        <Input
                            id="vo_reason"
                            name="reason"
                            placeholder={t('e.g. Requested by client on site')}
                        />
                        <InputError message={errors.reason} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="attachments">
                            {t('Photos or drawings')}
                        </Label>
                        <Input
                            id="attachments"
                            name="attachments[]"
                            type="file"
                            multiple
                            accept="image/*,application/pdf"
                        />
                        <InputError
                            message={
                                errors.attachments ?? errors['attachments.0']
                            }
                        />
                    </div>
                </>
            )}
        </FormDialog>
    );
}

function RecordPayment({
    jobId,
    milestones,
}: {
    jobId: number;
    milestones: Milestone[];
}) {
    const { t } = useTranslation();
    const [method, setMethod] = useState('transfer');

    return (
        <FormDialog
            action={storePayment.form(jobId)}
            trigger={
                <Button size="sm" variant="outline">
                    <Plus />
                    {t('Record payment')}
                </Button>
            }
            title={t('Record a payment')}
            description={t(
                'The client will be asked to confirm they paid this.',
            )}
            submitLabel={t('Record payment')}
        >
            {(errors) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="pay_amount">
                                {t('Amount (RM)')}
                            </Label>
                            <Input
                                id="pay_amount"
                                name="amount"
                                inputMode="decimal"
                            />
                            <InputError message={errors.amount} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="paid_on">
                                {t('Date received')}
                            </Label>
                            <DatePicker
                                id="paid_on"
                                name="paid_on"
                                invalid={!!errors.paid_on}
                            />
                            <InputError message={errors.paid_on} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="method">{t('Method')}</Label>
                            <Select value={method} onValueChange={setMethod}>
                                <SelectTrigger id="method" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="transfer">
                                        {t('Bank transfer')}
                                    </SelectItem>
                                    <SelectItem value="cash">
                                        {t('Cash')}
                                    </SelectItem>
                                    <SelectItem value="cheque">
                                        {t('Cheque')}
                                    </SelectItem>
                                    <SelectItem value="other">
                                        {t('Other')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <input type="hidden" name="method" value={method} />
                            <InputError message={errors.method} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reference">{t('Reference')}</Label>
                            <Input
                                id="reference"
                                name="reference"
                                placeholder={t('e.g. MBB 88213')}
                            />
                            <InputError message={errors.reference} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="milestone_id">
                            {t('For milestone')}
                        </Label>
                        <MilestoneSelect
                            milestones={milestones}
                            name="milestone_id"
                            optional
                            placeholder={t('Choose a milestone')}
                        />
                        <InputError message={errors.milestone_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="receipt">
                            {t('Receipt (optional)')}
                        </Label>
                        <Input
                            id="receipt"
                            name="receipt"
                            type="file"
                            accept="image/*,application/pdf"
                        />
                        <InputError message={errors.receipt} />
                    </div>
                </>
            )}
        </FormDialog>
    );
}

JobShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Jobs', href: index() },
        { title: props.job.title, href: show(props.job.id) },
    ],
});
