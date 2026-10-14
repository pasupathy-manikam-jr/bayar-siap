import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { useTranslation } from '@/hooks/use-translation';

type Tone = 'quiet' | 'waiting' | 'active' | 'done' | 'final' | 'problem';

const toneOf: Record<string, Tone> = {
    draft: 'quiet',
    planned: 'quiet',
    cancelled: 'quiet',
    withdrawn: 'quiet',
    sent: 'waiting',
    open: 'waiting',
    pending: 'waiting',
    requested: 'waiting',
    in_progress: 'active',
    active: 'active',
    approved: 'done',
    signed: 'done',
    confirmed: 'done',
    paid: 'final',
    completed: 'final',
    rejected: 'problem',
    disputed: 'problem',
};

const toneClass: Record<Tone, string> = {
    quiet: 'bg-muted text-muted-foreground',
    waiting: 'bg-status-waiting/12 text-status-waiting',
    active: 'bg-status-active/12 text-status-active',
    done: 'bg-status-done/12 text-status-done',
    final: 'bg-status-done text-background',
    problem: 'bg-status-problem/12 text-status-problem',
};

/** Every status's English label (also the translation key); audiences override some below. */
const statusLabels: Record<string, string> = {
    draft: 'Draft',
    planned: 'Planned',
    cancelled: 'Cancelled',
    withdrawn: 'Withdrawn',
    active: 'Active',
    approved: 'Approved',
    signed: 'Signed',
    confirmed: 'Confirmed',
    paid: 'Paid',
    completed: 'Completed',
    rejected: 'Rejected',
    disputed: 'Disputed',
};

const labels: Record<'client' | 'contractor', Record<string, string>> = {
    client: {
        in_progress: 'In progress',
        requested: 'Payment requested',
        sent: 'Awaiting you',
        open: 'Awaiting you',
        pending: 'To confirm',
    },
    contractor: {
        in_progress: 'In progress',
        requested: 'Payment requested',
        sent: 'Sent to client',
        open: 'Awaiting client',
        pending: 'Awaiting client',
    },
};

/** Same colours on both sides (semantic status tokens); wording depends on who is reading. */
export default function StatusBadge({
    status,
    audience = 'client',
}: {
    status: string;
    audience?: 'client' | 'contractor';
}) {
    const { t } = useTranslation();
    const label =
        labels[audience][status] ??
        statusLabels[status] ??
        status.charAt(0).toUpperCase() + status.slice(1);

    return (
        <Badge
            className={cn(
                'border-transparent',
                toneClass[toneOf[status] ?? 'quiet'],
            )}
        >
            {t(label)}
        </Badge>
    );
}
