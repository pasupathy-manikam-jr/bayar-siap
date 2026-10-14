import { Form, Head, usePage } from '@inertiajs/react';
import { Clock, FileText, MapPin, StickyNote } from 'lucide-react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import CommentThread from '@/components/comment-thread';
import type { ThreadComment } from '@/components/comment-thread';
import BankDetails from '@/components/client/bank-details';
import JobHeader from '@/components/client/job-header';
import ReasonDialog from '@/components/reason-dialog';
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
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, formatMoney, formatCoords } from '@/lib/format';
import { approve, reject } from '@/routes/client/payment-requests';
import { store as storeComment } from '@/routes/client/comments';
import { useTranslation } from '@/hooks/use-translation';
import type { ClientJob, Contractor } from '@/types/client';

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
    job: ClientJob;
    contractor: Contractor;
    paymentRequest: {
        id: number;
        amount_cents: number;
        note: string | null;
        status: 'open' | 'approved' | 'rejected' | 'withdrawn';
        reject_reason: string | null;
        verbal_approval_note: string | null;
        requested_at: string | null;
        decided_at: string | null;
    };
    milestone: {
        id: number;
        name: string;
        description: string | null;
        sequence: number;
    };
    proofs: Proof[];
    comments: ThreadComment[];
};

export default function PaymentRequest({
    job,
    contractor,
    paymentRequest,
    milestone,
    proofs,
    comments,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const [viewing, setViewing] = useState<Proof | null>(null);
    const args = {
        shareToken: job.share_token,
        paymentRequest: paymentRequest.id,
    };
    const photos = proofs.filter((p) => p.type === 'photo');
    const others = proofs.filter((p) => p.type !== 'photo');

    return (
        <>
            <Head
                title={t('Payment request · :name', { name: milestone.name })}
            />
            <JobHeader job={job} contractor={contractor} back />

            <div className="space-y-6">
                {errors.rule && <AlertError errors={[errors.rule]} />}

                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <CardDescription>
                                    {t(
                                        'Milestone :sequence · requested :date',
                                        {
                                            sequence: milestone.sequence,
                                            date: formatDateTime(
                                                paymentRequest.requested_at,
                                            ),
                                        },
                                    )}
                                </CardDescription>
                                <CardTitle className="mt-1 text-lg">
                                    {milestone.name}
                                </CardTitle>
                            </div>
                            <StatusBadge status={paymentRequest.status} />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="font-display text-4xl font-semibold tabular-nums">
                            {formatMoney(paymentRequest.amount_cents)}
                        </p>
                        {milestone.description && (
                            <p className="text-sm text-muted-foreground">
                                {milestone.description}
                            </p>
                        )}
                        {paymentRequest.note && (
                            <p className="rounded-md bg-muted p-3 text-sm">
                                {paymentRequest.note}
                            </p>
                        )}
                        {paymentRequest.status === 'rejected' && (
                            <p className="text-sm text-destructive">
                                {t('You rejected this on :date: :reason', {
                                    date: formatDateTime(
                                        paymentRequest.decided_at,
                                    ),
                                    reason: paymentRequest.reject_reason ?? '',
                                })}
                            </p>
                        )}
                        {paymentRequest.verbal_approval_note && (
                            <p className="rounded-md border border-status-waiting/40 bg-status-waiting/10 p-3 text-sm">
                                {t(
                                    ':name marked this as approved by you verbally: “:note”. If that’s not right, say so in the comments below.',
                                    {
                                        name: contractor.name,
                                        note: paymentRequest.verbal_approval_note,
                                    },
                                )}
                            </p>
                        )}
                        {paymentRequest.status === 'approved' &&
                            !paymentRequest.verbal_approval_note && (
                                <p className="text-sm text-status-done">
                                    {t('You approved this on :date.', {
                                        date: formatDateTime(
                                            paymentRequest.decided_at,
                                        ),
                                    })}
                                </p>
                            )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Proof of work')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Photos are time- and location-stamped when taken and can’t be edited after this request was sent.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {photos.length > 0 && (
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {photos.map((photo) => (
                                    <button
                                        key={photo.id}
                                        type="button"
                                        onClick={() => setViewing(photo)}
                                        className="group overflow-hidden rounded-md border text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {photo.thumb_url && (
                                            <img
                                                src={photo.thumb_url}
                                                alt={
                                                    photo.caption ??
                                                    t('Proof photo')
                                                }
                                                className="aspect-square w-full object-cover transition group-hover:opacity-90"
                                                loading="lazy"
                                            />
                                        )}
                                        <span className="flex items-center gap-1 px-2 py-1 text-xs text-muted-foreground">
                                            <Clock className="size-3" />
                                            {formatDateTime(photo.captured_at)}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        )}
                        {others.map((proof) => (
                            <div
                                key={proof.id}
                                className="flex gap-2 rounded-md border p-3 text-sm"
                            >
                                {proof.type === 'note' ? (
                                    <StickyNote className="size-4 shrink-0 text-muted-foreground" />
                                ) : (
                                    <FileText className="size-4 shrink-0 text-muted-foreground" />
                                )}
                                <div className="min-w-0">
                                    {proof.caption}
                                    {proof.url && (
                                        <a
                                            href={proof.url}
                                            className="block font-medium underline decoration-primary decoration-2 underline-offset-4"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            {t('Open file')}
                                        </a>
                                    )}
                                </div>
                            </div>
                        ))}
                        {proofs.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('No proof attached.')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                {paymentRequest.status === 'open' && (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Pay to')}</CardTitle>
                                <CardDescription>
                                    {t(
                                        'After approving, transfer the amount and your contractor records it for you to confirm.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <BankDetails contractor={contractor} />
                            </CardContent>
                        </Card>

                        <div className="flex flex-col gap-2 sm:flex-row-reverse">
                            <Form
                                noValidate
                                {...approve.form(args)}
                                className="sm:flex-1"
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        size="lg"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('Approve :amount', {
                                            amount: formatMoney(
                                                paymentRequest.amount_cents,
                                            ),
                                        })}
                                    </Button>
                                )}
                            </Form>
                            <ReasonDialog
                                action={reject.form(args)}
                                field="reason"
                                trigger={
                                    <Button
                                        type="button"
                                        size="lg"
                                        variant="outline"
                                        className="sm:flex-1"
                                    >
                                        {t('Reject')}
                                    </Button>
                                }
                                title={t('Reject this payment request')}
                                description={t(
                                    'The milestone goes back to your contractor to fix. Your reason is saved on the job record.',
                                )}
                                label={t('Reason')}
                                submitLabel={t('Reject request')}
                            />
                        </div>
                    </>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Questions or comments')}</CardTitle>
                        <CardDescription>
                            {t('Your contractor sees these on the job.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CommentThread
                            comments={comments}
                            action={storeComment.form(job.share_token)}
                            commentable={{
                                type: 'milestone',
                                id: milestone.id,
                            }}
                            viewer="client"
                        />
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => !open && setViewing(null)}
            >
                <DialogContent className="max-w-[calc(100%-1rem)] p-2 sm:max-w-4xl">
                    <DialogTitle className="sr-only">
                        {t('Proof photo')}
                    </DialogTitle>
                    {viewing && (
                        <>
                            <img
                                src={viewing.url ?? undefined}
                                alt={viewing.caption ?? t('Proof photo')}
                                className="max-h-[75vh] w-full rounded object-contain"
                            />
                            <DialogDescription className="space-y-1 px-2 pb-2 text-xs">
                                {viewing.caption && (
                                    <span className="block text-sm text-foreground">
                                        {viewing.caption}
                                    </span>
                                )}
                                <span className="flex flex-wrap items-center gap-x-3">
                                    <span>
                                        <Clock className="mr-1 inline size-3" />
                                        {t('Taken :date', {
                                            date: formatDateTime(
                                                viewing.captured_at,
                                            ),
                                        })}
                                    </span>
                                    {viewing.lat && viewing.lng && (
                                        <a
                                            href={`https://maps.google.com/?q=${viewing.lat},${viewing.lng}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="underline"
                                        >
                                            <MapPin className="mr-1 inline size-3" />
                                            {formatCoords(
                                                viewing.lat,
                                                viewing.lng,
                                            )}
                                        </a>
                                    )}
                                </span>
                                {viewing.sha256 && (
                                    <span className="block font-mono break-all">
                                        SHA-256 {viewing.sha256}
                                    </span>
                                )}
                            </DialogDescription>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
