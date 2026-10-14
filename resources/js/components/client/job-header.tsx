import { Link } from '@inertiajs/react';
import { ArrowLeft, MapPin } from 'lucide-react';
import StatusBadge from '@/components/status-badge';
import { show } from '@/routes/client';
import type { ClientJob, Contractor } from '@/types/client';
import { useTranslation } from '@/hooks/use-translation';

export default function JobHeader({
    job,
    contractor,
    back = false,
}: {
    job: ClientJob;
    contractor: Contractor;
    back?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <header className="mb-6 space-y-3">
            {back && (
                <Link
                    href={show(job.share_token)}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    {t('Back to job')}
                </Link>
            )}
            <div className="flex items-center gap-3">
                {contractor.logo_url ? (
                    <img
                        src={contractor.logo_url}
                        alt=""
                        className="size-10 rounded-md object-cover"
                    />
                ) : (
                    <div className="flex size-10 items-center justify-center rounded-md bg-primary font-semibold text-primary-foreground">
                        {contractor.name.charAt(0)}
                    </div>
                )}
                <div className="min-w-0">
                    <p className="truncate text-sm text-muted-foreground">
                        {contractor.name}
                    </p>
                    <h1 className="truncate text-xl font-semibold">
                        {job.title}
                    </h1>
                </div>
                <div className="ml-auto">
                    <StatusBadge status={job.status} />
                </div>
            </div>
            <p className="flex items-center gap-1 text-sm text-muted-foreground">
                <MapPin className="size-4 shrink-0" />
                {job.site_address}
            </p>
        </header>
    );
}
