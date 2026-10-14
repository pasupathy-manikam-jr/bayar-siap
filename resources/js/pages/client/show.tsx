import { Form, Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ChevronRight, Download, FileSignature, Receipt } from 'lucide-react';
import AlertError from '@/components/alert-error';
import JobHeader from '@/components/client/job-header';
import ReasonDialog from '@/components/reason-dialog';
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
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatMoney, PAYMENT_METHOD_LABELS } from '@/lib/format';
import { accept, record } from '@/routes/client';
import { show as showPaymentRequest } from '@/routes/client/payment-requests';
import { confirm, dispute } from '@/routes/client/payments';
import { show as showVariationOrder } from '@/routes/client/variation-orders';
import { useTranslation } from '@/hooks/use-translation';
import type { ClientJob, Contractor, JobSummary } from '@/types/client';
import type { RouteDefinition } from '@/wayfinder';

type Milestone = {
    id: number;
    sequence: number;
    name: string;
    amount_cents: number;
    due_date: string | null;
    status: string;
    open_request_id: number | null;
};

type VariationOrder = {
    id: number;
    number: string;
    description: string;
    amount_cents: number;
    status: string;
};

type Payment = {
    id: number;
    amount_cents: number;
    paid_on: string;
    method: string;
    reference: string | null;
    client_status: 'pending' | 'confirmed' | 'disputed';
    milestone: string | null;
};

type Props = {
    job: ClientJob;
    contractor: Contractor;
    summary: JobSummary;
    milestones: Milestone[];
    variationOrders: VariationOrder[];
    payments: Payment[];
};

export default function Show({
    job,
    contractor,
    summary,
    milestones,
    variationOrders,
    payments,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const token = job.share_token;
    const openRequests = milestones.filter((m) => m.open_request_id);
    const unsignedVos = variationOrders.filter((vo) => vo.status === 'sent');
    const paidPercent =
        summary.total_cents > 0
            ? Math.min(
                  100,
                  Math.round(
                      (summary.confirmed_cents / summary.total_cents) * 100,
                  ),
              )
            : 0;

    return (
        <>
            <Head title={job.title} />
            <JobHeader job={job} contractor={contractor} />

            <div className="space-y-6">
                {errors.rule && <AlertError errors={[errors.rule]} />}

                {job.status === 'cancelled' && (
                    <p className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm">
                        {job.cancel_reason
                            ? t(
                                  'This job was cancelled. Reason: “:reason”. The record stays here for you to download.',
                                  { reason: job.cancel_reason },
                              )
                            : t(
                                  'This job was cancelled. The record stays here for you to download.',
                              )}
                    </p>
                )}

                {job.status === 'sent' && (
                    <Card className="border-primary">
                        <CardHeader>
                            <CardTitle>
                                {t('Review and accept this job')}
                            </CardTitle>
                            <CardDescription>
                                {t(
                                    'Check the total and the payment schedule below. Accepting locks the quoted price; any later change needs a variation order you sign.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form noValidate {...accept.form(token)}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        className="w-full sm:w-auto"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('Accept job and schedule')}
                                    </Button>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-3 gap-3 text-center">
                            <Stat
                                label={t('Job total')}
                                cents={summary.total_cents}
                            />
                            <Stat
                                label={t('Paid')}
                                cents={summary.confirmed_cents}
                            />
                            <Stat
                                label={t('Balance')}
                                cents={summary.balance_cents}
                            />
                        </div>
                        <TapeProgress
                            percent={paidPercent}
                            label={t('Paid so far')}
                        />
                        {summary.total_cents !== summary.quote_cents && (
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Quote :amount plus signed variation orders.',
                                    {
                                        amount: formatMoney(
                                            summary.quote_cents,
                                        ),
                                    },
                                )}
                            </p>
                        )}
                    </CardContent>
                </Card>

                {(openRequests.length > 0 || unsignedVos.length > 0) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Waiting for you')}</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y p-0">
                            {openRequests.map((m) => (
                                <ActionRow
                                    key={`pr-${m.id}`}
                                    href={showPaymentRequest({
                                        shareToken: token,
                                        paymentRequest: m.open_request_id!,
                                    })}
                                    icon={<Receipt className="size-5" />}
                                    title={t('Payment request: :name', {
                                        name: m.name,
                                    })}
                                    amount={m.amount_cents}
                                />
                            ))}
                            {unsignedVos.map((vo) => (
                                <ActionRow
                                    key={`vo-${vo.id}`}
                                    href={showVariationOrder({
                                        shareToken: token,
                                        variationOrder: vo.id,
                                    })}
                                    icon={<FileSignature className="size-5" />}
                                    title={t(':number to sign: :description', {
                                        number: vo.number,
                                        description: vo.description,
                                    })}
                                    amount={vo.amount_cents}
                                />
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Milestones')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Each stage is paid after you approve its proof.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <ol className="divide-y">
                            {milestones.map((m) => (
                                <li
                                    key={m.id}
                                    className="flex items-center gap-3 px-6 py-3"
                                >
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                        {m.sequence}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {m.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {t('Due :date', {
                                                date: formatDate(m.due_date),
                                            })}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm font-medium tabular-nums">
                                            {formatMoney(m.amount_cents)}
                                        </p>
                                        <StatusBadge status={m.status} />
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

                {variationOrders.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Variation orders')}</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y p-0">
                            {variationOrders.map((vo) => (
                                <ActionRow
                                    key={vo.id}
                                    href={showVariationOrder({
                                        shareToken: token,
                                        variationOrder: vo.id,
                                    })}
                                    title={`${vo.number}: ${vo.description}`}
                                    amount={vo.amount_cents}
                                    status={vo.status}
                                />
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Payments')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Payments your contractor has recorded. Confirm the ones you made so both sides agree on the balance.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {payments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No payments recorded yet.')}
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('Payment')}</TableHead>
                                        <TableHead className="text-right">
                                            {t('Your confirmation')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payments.map((p) => (
                                        <TableRow key={p.id}>
                                            <TableCell className="align-top whitespace-normal">
                                                <span className="block font-semibold tabular-nums">
                                                    {formatMoney(
                                                        p.amount_cents,
                                                    )}
                                                </span>
                                                <span className="block text-sm">
                                                    {formatDate(p.paid_on)} ·{' '}
                                                    {t(
                                                        PAYMENT_METHOD_LABELS[
                                                            p.method
                                                        ] ?? p.method,
                                                    )}
                                                    {p.reference &&
                                                        ` · ${p.reference}`}
                                                </span>
                                                {p.milestone && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {p.milestone}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right align-top">
                                                {p.client_status ===
                                                'pending' ? (
                                                    <PaymentActions
                                                        token={token}
                                                        payment={p}
                                                    />
                                                ) : (
                                                    <StatusBadge
                                                        status={p.client_status}
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

                <Button variant="outline" className="w-full" asChild>
                    <a href={record.url(token)}>
                        <Download />
                        {t('Download full job record (PDF)')}
                    </a>
                </Button>
            </div>
        </>
    );
}

function Stat({ label, cents }: { label: string; cents: number }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="font-display text-lg font-semibold tabular-nums sm:text-2xl">
                {formatMoney(cents)}
            </p>
        </div>
    );
}

function ActionRow({
    href,
    icon,
    title,
    amount,
    status,
}: {
    href: RouteDefinition<'get'>;
    icon?: ReactNode;
    title: string;
    amount: number;
    status?: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 px-6 py-3 hover:bg-muted/50"
        >
            {icon && <span className="text-foreground">{icon}</span>}
            <span className="min-w-0 flex-1 truncate">{title}</span>
            <span className="text-right">
                <span className="block text-sm font-medium tabular-nums">
                    {formatMoney(amount)}
                </span>
                {status && <StatusBadge status={status} />}
            </span>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
        </Link>
    );
}

function PaymentActions({
    token,
    payment,
}: {
    token: string;
    payment: Payment;
}) {
    const { t } = useTranslation();
    const args = { shareToken: token, payment: payment.id };

    return (
        <div className="flex flex-col items-end gap-1">
            <Form noValidate {...confirm.form(args)}>
                {({ processing }) => (
                    <Button type="submit" size="sm" disabled={processing}>
                        {processing && <Spinner />}
                        {t('Confirm')}
                    </Button>
                )}
            </Form>
            <ReasonDialog
                action={dispute.form(args)}
                field="note"
                trigger={
                    <Button type="button" size="sm" variant="ghost">
                        {t('Dispute')}
                    </Button>
                }
                title={t('Dispute :amount', {
                    amount: formatMoney(payment.amount_cents),
                })}
                description={t(
                    "Tell your contractor what doesn't match — for example, the amount or that you never made this payment.",
                )}
                label={t('What is wrong?')}
                submitLabel={t('Send dispute')}
            />
        </div>
    );
}
