import { Form, Head, usePage } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import AlertError from '@/components/alert-error';
import CommentThread from '@/components/comment-thread';
import type { ThreadComment } from '@/components/comment-thread';
import JobHeader from '@/components/client/job-header';
import ReasonDialog from '@/components/reason-dialog';
import SignaturePad from '@/components/client/signature-pad';
import StatusBadge from '@/components/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, formatMoney } from '@/lib/format';
import { reject, sign } from '@/routes/client/variation-orders';
import { store as storeComment } from '@/routes/client/comments';
import { useTranslation } from '@/hooks/use-translation';
import type { ClientJob, Contractor } from '@/types/client';

type Props = {
    job: ClientJob;
    contractor: Contractor;
    variationOrder: {
        id: number;
        number: string;
        description: string;
        reason: string | null;
        amount_cents: number;
        status: 'sent' | 'signed' | 'rejected';
        signer_name: string | null;
        signed_at: string | null;
        reject_reason: string | null;
        target_milestone: string | null;
    };
    attachments: { id: number; url: string; is_pdf: boolean }[];
    comments: ThreadComment[];
};

export default function VariationOrder({
    job,
    contractor,
    variationOrder: vo,
    attachments,
    comments,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const args = { shareToken: job.share_token, variationOrder: vo.id };
    const isDeduction = vo.amount_cents < 0;

    return (
        <>
            <Head title={`${vo.number} · ${job.title}`} />
            <JobHeader job={job} contractor={contractor} back />

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <CardDescription>
                                    {t('Variation order :number', {
                                        number: vo.number,
                                    })}
                                </CardDescription>
                                <CardTitle className="mt-1 text-lg">
                                    {isDeduction
                                        ? t('Work removed')
                                        : t('Extra work')}
                                </CardTitle>
                            </div>
                            <StatusBadge status={vo.status} />
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p
                            className={`font-display text-4xl font-semibold tabular-nums ${isDeduction ? 'text-status-done' : ''}`}
                        >
                            {isDeduction ? '−' : '+'}
                            {formatMoney(Math.abs(vo.amount_cents))}
                        </p>
                        <p className="whitespace-pre-line">{vo.description}</p>
                        {vo.reason && (
                            <p className="text-sm text-muted-foreground">
                                {t('Why: :reason', { reason: vo.reason })}
                            </p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            {vo.target_milestone
                                ? t(
                                      'Signing adjusts the milestone “:milestone”.',
                                      {
                                          milestone: vo.target_milestone,
                                      },
                                  )
                                : t('Signing adds a new milestone to the job.')}
                        </p>

                        {attachments.length > 0 && (
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {attachments.map((a) =>
                                    a.is_pdf ? (
                                        <a
                                            key={a.id}
                                            href={a.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex aspect-square flex-col items-center justify-center gap-1 rounded-md border text-sm text-muted-foreground hover:bg-muted"
                                        >
                                            <FileText className="size-6" />
                                            {t('Drawing (PDF)')}
                                        </a>
                                    ) : (
                                        <a
                                            key={a.id}
                                            href={a.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <img
                                                src={a.url}
                                                alt={t(
                                                    'Variation order attachment',
                                                )}
                                                className="aspect-square w-full rounded-md border object-cover"
                                                loading="lazy"
                                            />
                                        </a>
                                    ),
                                )}
                            </div>
                        )}

                        {vo.status === 'signed' && (
                            <p className="text-sm text-status-done">
                                {t('Signed by :name on :date.', {
                                    name: vo.signer_name ?? '',
                                    date: formatDateTime(vo.signed_at),
                                })}
                            </p>
                        )}
                        {vo.status === 'rejected' && (
                            <p className="text-sm text-destructive">
                                {t('You rejected this: :reason', {
                                    reason: vo.reject_reason ?? '',
                                })}
                            </p>
                        )}
                    </CardContent>
                </Card>

                {vo.status === 'sent' && job.status !== 'cancelled' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Sign to agree')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'Once signed, the job total changes by this amount.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                noValidate
                                {...sign.form(args)}
                                className="space-y-5"
                            >
                                {({ processing, errors: formErrors }) => (
                                    <>
                                        {errors.rule && (
                                            <AlertError
                                                errors={[errors.rule]}
                                            />
                                        )}
                                        <div className="grid gap-2">
                                            <Label htmlFor="signer_name">
                                                {t('Your full name')}
                                            </Label>
                                            <Input
                                                id="signer_name"
                                                name="signer_name"
                                                autoComplete="name"
                                                defaultValue={job.client_name}
                                            />
                                            <InputError
                                                message={formErrors.signer_name}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>{t('Signature')}</Label>
                                            <SignaturePad name="signature" />
                                            <InputError
                                                message={formErrors.signature}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label className="flex items-start gap-3 font-normal">
                                                <Checkbox
                                                    name="agree"
                                                    className="mt-0.5"
                                                />
                                                <span>
                                                    {t(
                                                        'I agree to :number and the change of :amount to the job total.',
                                                        {
                                                            number: vo.number,
                                                            amount: `${isDeduction ? '−' : '+'}${formatMoney(Math.abs(vo.amount_cents))}`,
                                                        },
                                                    )}
                                                </span>
                                            </Label>
                                            <InputError
                                                message={formErrors.agree}
                                            />
                                        </div>

                                        <div className="flex flex-col gap-2 sm:flex-row-reverse">
                                            <Button
                                                type="submit"
                                                size="lg"
                                                className="sm:flex-1"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {t('Sign :number', {
                                                    number: vo.number,
                                                })}
                                            </Button>
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
                                                title={t('Reject :number', {
                                                    number: vo.number,
                                                })}
                                                description={t(
                                                    'The job total stays the same. Your reason is saved on the job record.',
                                                )}
                                                label={t('Reason')}
                                                submitLabel={t(
                                                    'Reject variation',
                                                )}
                                            />
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
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
                            commentable={{ type: 'variation_order', id: vo.id }}
                            viewer="client"
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
