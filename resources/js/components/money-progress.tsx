import TapeProgress from '@/components/tape-progress';
import { formatMoney } from '@/lib/format';
import type { JobSummary } from '@/types/client';
import { useTranslation } from '@/hooks/use-translation';

/** Confirmed payments against the job total. */
export default function MoneyProgress({ summary }: { summary: JobSummary }) {
    const { t } = useTranslation();
    const percent =
        summary.total_cents > 0
            ? (summary.confirmed_cents / summary.total_cents) * 100
            : 0;

    return (
        <div className="flex min-w-36 flex-col gap-1">
            <TapeProgress percent={percent} label={t('Paid so far')} />
            <p className="text-xs text-muted-foreground tabular-nums">
                {t(':paid of :total', {
                    paid: formatMoney(summary.confirmed_cents),
                    total: formatMoney(summary.total_cents),
                })}
            </p>
        </div>
    );
}
