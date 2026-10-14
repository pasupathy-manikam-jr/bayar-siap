import { cn } from '@/lib/utils';

/**
 * Progress drawn as a site measuring tape: signal-yellow fill, a tick every 10%, a taller one at 50%.
 * The one decorative signature of the app; everything around it stays plain.
 */
export default function TapeProgress({
    percent,
    label,
    className,
}: {
    percent: number;
    label: string;
    className?: string;
}) {
    const value = Math.max(0, Math.min(100, Math.round(percent)));

    return (
        <div
            role="progressbar"
            aria-valuenow={value}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label={label}
            className={cn(
                'relative h-2.5 overflow-hidden rounded-sm bg-muted ring-1 ring-border ring-inset',
                className,
            )}
        >
            <div className="h-full bg-primary" style={{ width: `${value}%` }} />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0"
                style={{
                    backgroundImage:
                        'repeating-linear-gradient(to right, transparent 0 calc(10% - 1px), color-mix(in oklch, var(--foreground) 35%, transparent) calc(10% - 1px) 10%)',
                    backgroundSize: '100% 45%',
                    backgroundRepeat: 'no-repeat',
                }}
            />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-y-0 left-1/2 w-px bg-foreground/45"
            />
        </div>
    );
}
