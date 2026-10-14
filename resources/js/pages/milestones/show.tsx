import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Clock,
    FileText,
    Lock,
    MapPin,
    StickyNote,
    Trash2,
    Upload,
} from 'lucide-react';
import AlertError from '@/components/alert-error';
import CommentThread from '@/components/comment-thread';
import type { ThreadComment } from '@/components/comment-thread';
import FormDialog from '@/components/form-dialog';
import InputError from '@/components/input-error';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { useTranslation } from '@/hooks/use-translation';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    formatCoords,
} from '@/lib/format';
import { store as storeComment } from '@/routes/comments';
import { index as jobsIndex, show as showJob } from '@/routes/jobs';
import { show, start } from '@/routes/milestones';
import { store as storeNote } from '@/routes/milestones/notes';
import { store as requestPayment } from '@/routes/milestones/payment-requests';
import { store as storeProofs } from '@/routes/milestones/proofs';
import { approveVerbally, withdraw } from '@/routes/payment-requests';
import { destroy as destroyProof } from '@/routes/proofs';

type Proof = {
    id: number;
    type: 'photo' | 'file' | 'note';
    caption: string | null;
    lat: string | null;
    lng: string | null;
    sha256: string | null;
    captured_at: string | null;
    url: string | null;
    thumb_url: string | null;
};

type Props = {
    job: { id: number; title: string; status: string };
    milestone: {
        id: number;
        sequence: number;
        name: string;
        description: string | null;
        amount_cents: number;
        status: string;
        due_date: string | null;
        proofs_locked: boolean;
    };
    proofs: Proof[];
    requests: {
        id: number;
        amount_cents: number;
        note: string | null;
        status: string;
        reject_reason: string | null;
        verbal_approval_note: string | null;
        created_at: string | null;
        decided_at: string | null;
    }[];
    comments: ThreadComment[];
};

export default function MilestoneShow({
    job,
    milestone,
    proofs,
    requests,
    comments,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const openRequest = requests.find((r) => r.status === 'open');
    const canAddProof = !milestone.proofs_locked && job.status === 'active';

    return (
        <>
            <Head title={`${milestone.name} · ${job.title}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="space-y-2">
                    <Link
                        href={showJob(job.id)}
                        className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        {job.title}
                    </Link>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-xl font-semibold">
                                    {milestone.sequence}. {milestone.name}
                                </h1>
                                <StatusBadge
                                    status={milestone.status}
                                    audience="contractor"
                                />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {t(':amount · due :date', {
                                    amount: formatMoney(milestone.amount_cents),
                                    date: formatDate(milestone.due_date),
                                })}
                            </p>
                            {milestone.description && (
                                <p className="mt-1 text-sm">
                                    {milestone.description}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {milestone.status === 'planned' &&
                                job.status === 'active' && (
                                    <Form
                                        noValidate
                                        {...start.form(milestone.id)}
                                    >
                                        {({ processing }) => (
                                            <Button disabled={processing}>
                                                {processing && <Spinner />}
                                                {t('Start milestone')}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            {milestone.status === 'in_progress' && (
                                <FormDialog
                                    action={requestPayment.form(milestone.id)}
                                    trigger={
                                        <Button>{t('Request payment')}</Button>
                                    }
                                    title={t('Request :amount', {
                                        amount: formatMoney(
                                            milestone.amount_cents,
                                        ),
                                    })}
                                    description={t(
                                        'The client gets the proof below and approves or rejects. Proof is locked while the request is open.',
                                    )}
                                    submitLabel={t('Send request')}
                                >
                                    {(formErrors) => (
                                        <div className="grid gap-2">
                                            <Label htmlFor="request_note">
                                                {t('Note to client (optional)')}
                                            </Label>
                                            <Textarea
                                                id="request_note"
                                                name="note"
                                                rows={3}
                                            />
                                            <InputError
                                                message={formErrors.note}
                                            />
                                        </div>
                                    )}
                                </FormDialog>
                            )}
                            {openRequest && (
                                <Form
                                    noValidate
                                    {...withdraw.form(openRequest.id)}
                                >
                                    {({ processing }) => (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            {t('Withdraw request')}
                                        </Button>
                                    )}
                                </Form>
                            )}
                            {openRequest && (
                                <FormDialog
                                    action={approveVerbally.form(
                                        openRequest.id,
                                    )}
                                    trigger={
                                        <Button variant="outline">
                                            {t('Mark approved verbally')}
                                        </Button>
                                    }
                                    title={t(
                                        'Client approved outside the app?',
                                    )}
                                    description={t(
                                        'Use this only when the client agreed by phone or in person. It is flagged as verbal on the job record and the client is told.',
                                    )}
                                    submitLabel={t('Mark approved')}
                                >
                                    {(formErrors) => (
                                        <div className="grid gap-2">
                                            <Label htmlFor="verbal_note">
                                                {t(
                                                    'How and when did they approve?',
                                                )}
                                            </Label>
                                            <Textarea
                                                id="verbal_note"
                                                name="note"
                                                rows={3}
                                                placeholder={t(
                                                    'e.g. Phone call with Puan Siti, 13 Oct 3pm',
                                                )}
                                            />
                                            <InputError
                                                message={formErrors.note}
                                            />
                                        </div>
                                    )}
                                </FormDialog>
                            )}
                        </div>
                    </div>
                </div>

                {errors.rule && <AlertError errors={[errors.rule]} />}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Proof')}</CardTitle>
                        <CardDescription>
                            {milestone.proofs_locked ? (
                                <span className="inline-flex items-center gap-1">
                                    <Lock className="size-3.5" />
                                    {t(
                                        'Locked: a payment request is open or this milestone is approved.',
                                    )}
                                </span>
                            ) : (
                                t(
                                    'Photos keep their capture time and GPS when the camera recorded them. Each file is fingerprinted (SHA-256).',
                                )
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {canAddProof && (
                            <div className="grid gap-4 lg:grid-cols-2">
                                <Form
                                    noValidate
                                    {...storeProofs.form(milestone.id)}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="space-y-3 rounded-lg border p-4"
                                >
                                    {({ processing, errors: formErrors }) => (
                                        <>
                                            <Label htmlFor="files">
                                                {t('Photos or files')}
                                            </Label>
                                            <Input
                                                id="files"
                                                name="files[]"
                                                type="file"
                                                multiple
                                                accept="image/*,application/pdf"
                                            />
                                            <Input
                                                name="caption"
                                                placeholder={t(
                                                    'Caption (optional)',
                                                )}
                                                aria-label={t('Caption')}
                                            />
                                            <InputError
                                                message={
                                                    formErrors.files ??
                                                    formErrors['files.0'] ??
                                                    formErrors.caption
                                                }
                                            />
                                            <Button
                                                size="sm"
                                                disabled={processing}
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Upload />
                                                )}
                                                {t('Upload')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                                <Form
                                    noValidate
                                    {...storeNote.form(milestone.id)}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="space-y-3 rounded-lg border p-4"
                                >
                                    {({ processing, errors: formErrors }) => (
                                        <>
                                            <Label htmlFor="note">
                                                {t('Site note')}
                                            </Label>
                                            <Textarea
                                                id="note"
                                                name="note"
                                                rows={3}
                                                placeholder={t(
                                                    'What was done today',
                                                )}
                                            />
                                            <InputError
                                                message={formErrors.note}
                                            />
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {t('Add note')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </div>
                        )}

                        {proofs.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'No proof yet. You need at least one before requesting payment.',
                                )}
                            </p>
                        ) : (
                            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {proofs.map((p) => (
                                    <li
                                        key={p.id}
                                        className="overflow-hidden rounded-lg border"
                                    >
                                        {p.type === 'photo' && p.url ? (
                                            <a
                                                href={p.url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <img
                                                    src={p.thumb_url ?? p.url}
                                                    alt={
                                                        p.caption ??
                                                        t('Proof photo')
                                                    }
                                                    className="aspect-[4/3] w-full object-cover"
                                                    loading="lazy"
                                                />
                                            </a>
                                        ) : (
                                            <div className="flex aspect-[4/3] items-center justify-center bg-muted text-muted-foreground">
                                                {p.type === 'note' ? (
                                                    <StickyNote className="size-8" />
                                                ) : (
                                                    <FileText className="size-8" />
                                                )}
                                            </div>
                                        )}
                                        <div className="space-y-1 p-3 text-xs">
                                            {p.caption && (
                                                <p className="text-sm">
                                                    {p.caption}
                                                </p>
                                            )}
                                            <p className="flex items-center gap-1 text-muted-foreground">
                                                <Clock className="size-3" />
                                                {formatDateTime(p.captured_at)}
                                            </p>
                                            {p.lat && p.lng && (
                                                <a
                                                    href={`https://maps.google.com/?q=${p.lat},${p.lng}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="flex items-center gap-1 text-muted-foreground hover:underline"
                                                >
                                                    <MapPin className="size-3" />
                                                    {formatCoords(p.lat, p.lng)}
                                                </a>
                                            )}
                                            {p.type === 'file' && p.url && (
                                                <a
                                                    href={p.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="underline"
                                                >
                                                    {t('Open file')}
                                                </a>
                                            )}
                                            {!milestone.proofs_locked && (
                                                <Form
                                                    noValidate
                                                    {...destroyProof.form(p.id)}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="-ml-2 text-destructive"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <Trash2 />
                                                            {t('Remove')}
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Payment requests')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {requests.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('None yet.')}
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Sent')}</TableHead>
                                        <TableHead>
                                            {t('Note / reason')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('Amount')}
                                        </TableHead>
                                        <TableHead className="text-right">
                                            {t('Status')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.map((r) => (
                                        <TableRow key={r.id}>
                                            <TableCell className="align-top">
                                                {formatDateTime(r.created_at)}
                                            </TableCell>
                                            <TableCell className="whitespace-normal">
                                                {r.note}
                                                {r.verbal_approval_note && (
                                                    <span className="block text-status-waiting">
                                                        {t(
                                                            'Approved verbally: “:note”',
                                                            {
                                                                note: r.verbal_approval_note,
                                                            },
                                                        )}
                                                    </span>
                                                )}
                                                {r.reject_reason && (
                                                    <span className="block text-destructive">
                                                        {t(
                                                            'Client: “:reason”',
                                                            {
                                                                reason: r.reject_reason,
                                                            },
                                                        )}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right align-top tabular-nums">
                                                {formatMoney(r.amount_cents)}
                                            </TableCell>
                                            <TableCell className="text-right align-top">
                                                <StatusBadge
                                                    status={r.status}
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
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Comments')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Visible to the client on their payment request page.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CommentThread
                            comments={comments}
                            action={storeComment.form()}
                            commentable={{
                                type: 'milestone',
                                id: milestone.id,
                            }}
                            viewer="contractor"
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MilestoneShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Jobs', href: jobsIndex() },
        { title: props.job.title, href: showJob(props.job.id) },
        { title: props.milestone.name, href: show(props.milestone.id) },
    ],
});
