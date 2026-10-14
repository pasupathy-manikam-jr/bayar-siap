import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { RouteFormDefinition } from '@/wayfinder';
import { useTranslation } from '@/hooks/use-translation';

export type ThreadComment = {
    id: number;
    body: string;
    from: 'client' | 'contractor';
    author: string;
    created_at: string | null;
};

/** A comment thread on a milestone or variation order, readable and writable by both sides. */
export default function CommentThread({
    comments,
    action,
    commentable,
    viewer,
}: {
    comments: ThreadComment[];
    action: RouteFormDefinition<'post'>;
    commentable: { type: 'milestone' | 'variation_order'; id: number };
    viewer: 'client' | 'contractor';
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-4">
            {comments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('No comments yet.')}
                </p>
            ) : (
                <ul className="space-y-3">
                    {comments.map((c) => {
                        const mine = c.from === viewer;

                        return (
                            <li
                                key={c.id}
                                className={cn(
                                    'max-w-[85%] rounded-lg px-3 py-2 text-sm',
                                    mine
                                        ? 'ml-auto bg-accent text-accent-foreground ring-1 ring-primary/50 ring-inset'
                                        : 'bg-muted',
                                )}
                            >
                                <p className="whitespace-pre-line">{c.body}</p>
                                <p
                                    className={cn(
                                        'mt-1 text-xs',
                                        mine
                                            ? 'text-muted-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {mine ? t('You') : c.author} ·{' '}
                                    {formatDateTime(c.created_at)}
                                </p>
                            </li>
                        );
                    })}
                </ul>
            )}

            <Form
                noValidate
                {...action}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="space-y-2"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="commentable_type"
                            value={commentable.type}
                        />
                        <input
                            type="hidden"
                            name="commentable_id"
                            value={commentable.id}
                        />
                        <Textarea
                            name="body"
                            rows={2}
                            placeholder={t('Write a comment')}
                            aria-label={t('Comment')}
                        />
                        <InputError message={errors.body ?? errors.rule} />
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('Post comment')}
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}
