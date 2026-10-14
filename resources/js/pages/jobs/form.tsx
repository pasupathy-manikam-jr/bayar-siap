import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import AlertError from '@/components/alert-error';
import DatePicker from '@/components/date-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney, toCents } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show, store, update } from '@/routes/jobs';

type MilestoneRow = {
    key: number;
    name: string;
    amount: string;
    due_date: string | null;
};

type Template = {
    name: string;
    milestones: { name: string; percent: number }[];
};

type Props = {
    clients: { id: number; name: string; phone: string }[];
    templates: Record<string, Template>;
    job?: {
        id: number;
        client_id: number;
        title: string;
        site_address: string;
        quote: string;
        start_date: string | null;
        target_date: string | null;
        milestones: Omit<MilestoneRow, 'key'>[];
    };
};

const NEW_CLIENT = 'new';

export default function JobForm({ clients, templates, job }: Props) {
    const { t } = useTranslation();
    const nextKey = useRef(1);
    const row = (m: Omit<MilestoneRow, 'key'>): MilestoneRow => ({
        ...m,
        key: nextKey.current++,
    });

    const form = useForm({
        client_id: job
            ? String(job.client_id)
            : clients.length
              ? ''
              : NEW_CLIENT,
        client_name: '',
        client_phone: '',
        client_email: '',
        title: job?.title ?? '',
        site_address: job?.site_address ?? '',
        quote: job?.quote ?? '',
        start_date: job?.start_date ?? '',
        target_date: job?.target_date ?? '',
        milestones: (job?.milestones ?? []).map(row),
    });
    const { data, setData, errors, processing } = form;
    const fieldErrors = errors as Record<string, string | undefined>;

    const quoteCents = toCents(data.quote);
    const milestoneCents = data.milestones.reduce(
        (sum, m) => sum + toCents(m.amount),
        0,
    );
    const difference = quoteCents - milestoneCents;

    const applyTemplate = (key: string) => {
        const template = templates[key];
        let allocated = 0;
        const rows = template.milestones.map((m, i) => {
            const isLast = i === template.milestones.length - 1;
            // The last stage takes the rounding remainder so the schedule matches the quote exactly.
            const cents = isLast
                ? quoteCents - allocated
                : Math.floor((quoteCents * m.percent) / 100);
            allocated += cents;

            return row({
                name: m.name,
                amount: quoteCents ? (cents / 100).toFixed(2) : '',
                due_date: null,
            });
        });
        setData('milestones', rows);
    };

    const updateRow = (key: number, patch: Partial<MilestoneRow>) =>
        setData(
            'milestones',
            data.milestones.map((m) =>
                m.key === key ? { ...m, ...patch } : m,
            ),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            client_id:
                values.client_id === NEW_CLIENT ? null : values.client_id,
            milestones: values.milestones.map(({ key: _key, ...m }) => m),
        }));

        if (job) {
            form.put(update.url(job.id));
        } else {
            form.post(store.url());
        }
    };

    return (
        <>
            <Head
                title={
                    job ? t('Edit :title', { title: job.title }) : t('New job')
                }
            />
            <form
                noValidate
                onSubmit={submit}
                className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4"
            >
                <h1 className="text-xl font-semibold">
                    {job ? t('Edit draft job') : t('New job')}
                </h1>

                {fieldErrors.rule && <AlertError errors={[fieldErrors.rule]} />}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Client')}</CardTitle>
                        <CardDescription>
                            {t(
                                'They get a link and confirm with a code sent to this phone. No app or account needed.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {clients.length > 0 && (
                            <div className="grid gap-2">
                                <Label htmlFor="client_id">{t('Client')}</Label>
                                <Select
                                    value={data.client_id}
                                    onValueChange={(value) =>
                                        setData('client_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="client_id"
                                        aria-invalid={!!errors.client_id}
                                    >
                                        <SelectValue
                                            placeholder={t('Choose a client')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {clients.map((c) => (
                                            <SelectItem
                                                key={c.id}
                                                value={String(c.id)}
                                            >
                                                {c.name} · {c.phone}
                                            </SelectItem>
                                        ))}
                                        <SelectItem value={NEW_CLIENT}>
                                            {t('+ New client')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.client_id} />
                            </div>
                        )}

                        {data.client_id === NEW_CLIENT && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id="client_name"
                                    label={t('Client name')}
                                    value={data.client_name}
                                    onChange={(v) => setData('client_name', v)}
                                    error={errors.client_name}
                                    autoComplete="off"
                                />
                                <Field
                                    id="client_phone"
                                    label={t('Mobile number')}
                                    value={data.client_phone}
                                    onChange={(v) => setData('client_phone', v)}
                                    error={errors.client_phone}
                                    placeholder="+60 12-345 6789"
                                    inputMode="tel"
                                />
                                <Field
                                    id="client_email"
                                    label={t('Email (optional)')}
                                    value={data.client_email}
                                    onChange={(v) => setData('client_email', v)}
                                    error={errors.client_email}
                                    inputMode="email"
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Job')}</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="title"
                            label={t('Title')}
                            value={data.title}
                            onChange={(v) => setData('title', v)}
                            error={errors.title}
                            placeholder={t('e.g. Kitchen renovation')}
                            className="sm:col-span-2"
                        />
                        <Field
                            id="site_address"
                            label={t('Site address')}
                            value={data.site_address}
                            onChange={(v) => setData('site_address', v)}
                            error={errors.site_address}
                            className="sm:col-span-2"
                        />
                        <Field
                            id="quote"
                            label={t('Quote total (RM)')}
                            value={data.quote}
                            onChange={(v) => setData('quote', v)}
                            error={errors.quote}
                            inputMode="decimal"
                            placeholder="48500.00"
                        />
                        <div />
                        <div className="grid gap-2">
                            <Label htmlFor="start_date">
                                {t('Start date')}
                            </Label>
                            <DatePicker
                                id="start_date"
                                name="start_date"
                                defaultValue={data.start_date}
                                onChange={(v) => setData('start_date', v)}
                                invalid={!!errors.start_date}
                            />
                            <InputError message={errors.start_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="target_date">
                                {t('Target completion')}
                            </Label>
                            <DatePicker
                                id="target_date"
                                name="target_date"
                                defaultValue={data.target_date}
                                onChange={(v) => setData('target_date', v)}
                                invalid={!!errors.target_date}
                            />
                            <InputError message={errors.target_date} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div className="space-y-1.5">
                            <CardTitle>{t('Payment schedule')}</CardTitle>
                            <CardDescription>
                                {t(
                                    'Milestones must add up to the quote before you can send the job.',
                                )}
                            </CardDescription>
                        </div>
                        <Select onValueChange={applyTemplate}>
                            <SelectTrigger
                                className="w-44"
                                aria-label={t('Use a template')}
                            >
                                <SelectValue
                                    placeholder={t('Use a template')}
                                />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(templates).map(
                                    ([key, template]) => (
                                        <SelectItem key={key} value={key}>
                                            {template.name}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {data.milestones.map((m, i) => (
                            <div
                                key={m.key}
                                className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[1fr_9rem_10rem_auto] sm:items-start sm:border-0 sm:p-0"
                            >
                                <div className="grid gap-1">
                                    <Input
                                        aria-label={t(
                                            'Milestone :number name',
                                            {
                                                number: i + 1,
                                            },
                                        )}
                                        value={m.name}
                                        onChange={(e) =>
                                            updateRow(m.key, {
                                                name: e.target.value,
                                            })
                                        }
                                        placeholder={t('Milestone :number', {
                                            number: i + 1,
                                        })}
                                        aria-invalid={
                                            !!fieldErrors[
                                                `milestones.${i}.name`
                                            ]
                                        }
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[`milestones.${i}.name`]
                                        }
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Input
                                        aria-label={t(
                                            'Milestone :number amount (RM)',
                                            {
                                                number: i + 1,
                                            },
                                        )}
                                        value={m.amount}
                                        onChange={(e) =>
                                            updateRow(m.key, {
                                                amount: e.target.value,
                                            })
                                        }
                                        inputMode="decimal"
                                        placeholder="RM"
                                        className="text-right tabular-nums"
                                        aria-invalid={
                                            !!fieldErrors[
                                                `milestones.${i}.amount`
                                            ]
                                        }
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                `milestones.${i}.amount`
                                            ]
                                        }
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <DatePicker
                                        name={`milestones.${i}.due_date`}
                                        defaultValue={m.due_date}
                                        placeholder={t('Due date')}
                                        onChange={(v) =>
                                            updateRow(m.key, { due_date: v })
                                        }
                                        invalid={
                                            !!fieldErrors[
                                                `milestones.${i}.due_date`
                                            ]
                                        }
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                `milestones.${i}.due_date`
                                            ]
                                        }
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Remove milestone :number', {
                                        number: i + 1,
                                    })}
                                    onClick={() =>
                                        setData(
                                            'milestones',
                                            data.milestones.filter(
                                                (x) => x.key !== m.key,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}

                        <InputError message={errors.milestones} />

                        <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setData('milestones', [
                                        ...data.milestones,
                                        row({
                                            name: '',
                                            amount: '',
                                            due_date: null,
                                        }),
                                    ])
                                }
                            >
                                <Plus />
                                {t('Add milestone')}
                            </Button>
                            <p
                                className={cn(
                                    'text-sm tabular-nums',
                                    difference === 0
                                        ? 'text-status-done'
                                        : 'text-status-waiting',
                                )}
                            >
                                {t(
                                    difference === 0
                                        ? ':allocated of :quote'
                                        : difference > 0
                                          ? ':allocated of :quote · :difference left to allocate'
                                          : ':allocated of :quote · :difference over the quote',
                                    {
                                        allocated: formatMoney(milestoneCents),
                                        quote: formatMoney(quoteCents),
                                        difference: formatMoney(
                                            Math.abs(difference),
                                        ),
                                    },
                                )}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href={job ? show(job.id) : index()}>
                            {t('Cancel')}
                        </Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {t('Save draft')}
                    </Button>
                </div>
            </form>
        </>
    );
}

function Field({
    id,
    label,
    value,
    onChange,
    error,
    className,
    ...props
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    className?: string;
} & Omit<React.ComponentProps<typeof Input>, 'value' | 'onChange' | 'id'>) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                aria-invalid={!!error}
                {...props}
            />
            <InputError message={error} />
        </div>
    );
}

JobForm.layout = {
    breadcrumbs: [{ title: 'Jobs', href: index() }],
};
