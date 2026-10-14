import { Landmark } from 'lucide-react';
import type { Contractor } from '@/types/client';
import { useTranslation } from '@/hooks/use-translation';

export default function BankDetails({
    contractor,
}: {
    contractor: Contractor;
}) {
    const { t } = useTranslation();

    if (!contractor.bank_account_no) {
        return (
            <p className="text-sm text-muted-foreground">
                {t('Ask :name for their bank details.', {
                    name: contractor.name,
                })}
            </p>
        );
    }

    return (
        <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
            <dt className="text-muted-foreground">
                <Landmark className="mr-1 inline size-4" />
                {t('Bank')}
            </dt>
            <dd>{contractor.bank_name}</dd>
            <dt className="text-muted-foreground">{t('Account name')}</dt>
            <dd>{contractor.bank_account_name}</dd>
            <dt className="text-muted-foreground">{t('Account no.')}</dt>
            <dd className="font-mono select-all">
                {contractor.bank_account_no}
            </dd>
        </dl>
    );
}
