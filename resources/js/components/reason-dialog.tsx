import type { ReactNode } from 'react';
import FormDialog from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { RouteFormDefinition } from '@/wayfinder';

/** Reject / dispute: the client must say why, and the contractor sees it on the record. */
export default function ReasonDialog({
    action,
    field,
    trigger,
    title,
    description,
    label,
    submitLabel,
}: {
    action: RouteFormDefinition<'post'>;
    field: 'reason' | 'note';
    trigger: ReactNode;
    title: string;
    description: string;
    label: string;
    submitLabel: string;
}) {
    return (
        <FormDialog
            action={action}
            trigger={trigger}
            title={title}
            description={description}
            submitLabel={submitLabel}
            destructive
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor={field}>{label}</Label>
                    <Textarea id={field} name={field} rows={4} />
                    <InputError message={errors[field]} />
                </div>
            )}
        </FormDialog>
    );
}
