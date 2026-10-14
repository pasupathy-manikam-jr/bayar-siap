import { Link } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
import { show as showMilestone } from '@/routes/milestones';

export type StripPhoto = {
    id: number;
    thumb_url: string;
    caption: string | null;
    captured_at: string | null;
    milestone_id: number;
    milestone: string;
    job: string;
};

/** Latest proof photos in one scrollable row; each opens its milestone. */
export default function PhotoStrip({
    photos,
    show,
}: {
    photos: StripPhoto[];
    show: 'job' | 'milestone';
}) {
    const { t } = useTranslation();

    if (photos.length === 0) {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Camera className="size-4" />
                {t(
                    'No photos yet. Proof photos appear here as milestones are documented.',
                )}
            </p>
        );
    }

    return (
        <ul className="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-2">
            {photos.map((photo) => (
                <li key={photo.id} className="w-44 shrink-0 snap-start">
                    <Link
                        href={showMilestone(photo.milestone_id)}
                        className="group flex flex-col gap-1.5 rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <img
                            src={photo.thumb_url}
                            alt={photo.caption ?? photo.milestone}
                            loading="lazy"
                            className="aspect-[4/3] w-full rounded-md border object-cover transition-opacity group-hover:opacity-90"
                        />
                        <span className="truncate text-sm font-medium">
                            {show === 'job' ? photo.job : photo.milestone}
                        </span>
                        <span className="truncate text-xs text-muted-foreground">
                            {show === 'job' ? `${photo.milestone} · ` : ''}
                            {formatDate(photo.captured_at)}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}
