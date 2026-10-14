import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import TapeProgress from '@/components/tape-progress';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
import { checkout, portal } from '@/routes/billing';
import { edit, update } from '@/routes/company';

type Props = {
    company: {
        name: string;
        bank_name: string | null;
        bank_account_name: string | null;
        bank_account_no: string | null;
        plan: string;
        storage_used_bytes: number;
        storage_limit_bytes: number | null;
        active_job_limit: number | null;
        has_billing_account: boolean;
        subscription_ends_at: string | null;
        logo_url: string | null;
    };
};

export default function Company({ company }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Company settings')} />

            <h1 className="sr-only">{t('Company settings')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('Company')}
                    description={t(
                        'Your business name, logo and the bank details clients see when you request payment',
                    )}
                />

                <Form
                    noValidate
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('Company name')}
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={company.name}
                                    autoComplete="organization"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="logo">{t('Logo')}</Label>
                                <div className="flex items-center gap-3">
                                    {company.logo_url && (
                                        <img
                                            src={company.logo_url}
                                            alt={t('Current logo')}
                                            className="size-12 rounded-md border object-cover"
                                        />
                                    )}
                                    <Input
                                        id="logo"
                                        name="logo"
                                        type="file"
                                        accept="image/*"
                                    />
                                </div>
                                <InputError message={errors.logo} />
                            </div>

                            <Heading
                                variant="small"
                                title={t('Bank details')}
                                description={t(
                                    'Shown to clients on payment requests',
                                )}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="bank_name">{t('Bank')}</Label>
                                <Input
                                    id="bank_name"
                                    name="bank_name"
                                    defaultValue={company.bank_name ?? ''}
                                    placeholder={t('e.g. Maybank')}
                                />
                                <InputError message={errors.bank_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_name">
                                    {t('Account name')}
                                </Label>
                                <Input
                                    id="bank_account_name"
                                    name="bank_account_name"
                                    defaultValue={
                                        company.bank_account_name ?? ''
                                    }
                                />
                                <InputError
                                    message={errors.bank_account_name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_no">
                                    {t('Account number')}
                                </Label>
                                <Input
                                    id="bank_account_no"
                                    name="bank_account_no"
                                    defaultValue={company.bank_account_no ?? ''}
                                    inputMode="numeric"
                                />
                                <InputError message={errors.bank_account_no} />
                            </div>

                            <PlanUsage company={company} />

                            <Button disabled={processing}>{t('Save')}</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Company.layout = {
    breadcrumbs: [
        {
            title: 'Company settings',
            href: edit(),
        },
    ],
};

const gb = (bytes: number) =>
    `${(bytes / 1024 ** 3).toFixed(bytes < 1024 ** 3 ? 2 : 1)} GB`;

function PlanUsage({ company }: Props) {
    const { storage_used_bytes: used, storage_limit_bytes: limit } = company;
    const percent = limit ? Math.min(100, Math.round((used / limit) * 100)) : 0;
    const { t } = useTranslation();

    return (
        <div className="space-y-2 rounded-lg border p-4 text-sm">
            <p>
                {t('Plan:')}{' '}
                <span className="font-medium capitalize">{company.plan}</span>
            </p>
            <p className="text-muted-foreground">
                {company.active_job_limit === null
                    ? t('Active jobs: unlimited')
                    : t('Active jobs: up to :limit', {
                          limit: company.active_job_limit,
                      })}
            </p>
            <p className="text-muted-foreground">
                {limit
                    ? t('Photo storage: :used of :limit', {
                          used: gb(used),
                          limit: gb(limit),
                      })
                    : t('Photo storage: :used (unlimited)', { used: gb(used) })}
            </p>
            {limit !== null && (
                <TapeProgress
                    percent={percent}
                    label={t('Photo storage used')}
                />
            )}
            {percent >= 90 && (
                <p className="text-destructive">
                    {t(
                        'Storage is nearly full. Remove unused proof or upgrade.',
                    )}
                </p>
            )}
            {company.subscription_ends_at && (
                <p className="text-status-waiting">
                    {t(
                        'Cancelled: Solo ends :date, then you’re back on Free.',
                        {
                            date: formatDate(company.subscription_ends_at),
                        },
                    )}
                </p>
            )}
            <div className="flex flex-wrap gap-2 pt-2">
                {company.plan === 'free' && (
                    <Form noValidate {...checkout.form()}>
                        {({ processing }) => (
                            <Button size="sm" disabled={processing}>
                                {processing && <Spinner />}
                                {t('Upgrade to Solo · RM 49/month')}
                            </Button>
                        )}
                    </Form>
                )}
                {company.has_billing_account && (
                    <Form noValidate {...portal.form()}>
                        {({ processing }) => (
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {t('Manage billing')}
                            </Button>
                        )}
                    </Form>
                )}
            </div>
        </div>
    );
}
