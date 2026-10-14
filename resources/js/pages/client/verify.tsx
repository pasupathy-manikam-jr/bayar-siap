import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { send, verify } from '@/routes/client/otp';

type Props = {
    job: { title: string; share_token: string };
    contractor: string;
    maskedPhone: string;
    codeSent: boolean;
};

export default function Verify({
    job,
    contractor,
    maskedPhone,
    codeSent,
}: Props) {
    const { t } = useTranslation();
    const [code, setCode] = useState('');

    return (
        <>
            <Head title={job.title} />
            <Card className="mx-auto max-w-md">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-accent text-accent-foreground">
                        <ShieldCheck className="size-6" />
                    </div>
                    <CardTitle className="text-lg">{job.title}</CardTitle>
                    <CardDescription>
                        {t(
                            ':contractor shared this job with you. To keep it private, we’ll text a 6-digit code to your phone ending :digits.',
                            { contractor, digits: maskedPhone.slice(-4) },
                        )}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {codeSent && (
                        <Form
                            noValidate
                            {...verify.form(job.share_token)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="flex flex-col items-center gap-2">
                                        <InputOTP
                                            name="code"
                                            maxLength={6}
                                            value={code}
                                            onChange={setCode}
                                            disabled={processing}
                                            pattern={REGEXP_ONLY_DIGITS}
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            autoFocus
                                        >
                                            <InputOTPGroup>
                                                {Array.from(
                                                    { length: 6 },
                                                    (_, index) => (
                                                        <InputOTPSlot
                                                            key={index}
                                                            index={index}
                                                        />
                                                    ),
                                                )}
                                            </InputOTPGroup>
                                        </InputOTP>
                                        <InputError message={errors.code} />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {t('Verify and open job')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}

                    <Form
                        noValidate
                        {...send.form(job.share_token)}
                        className="text-center"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Button
                                    type="submit"
                                    variant={codeSent ? 'link' : 'default'}
                                    className={codeSent ? '' : 'w-full'}
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {codeSent
                                        ? t('Send a new code')
                                        : t('Send code')}
                                </Button>
                                {!codeSent && (
                                    <InputError
                                        message={errors.code}
                                        className="mt-2"
                                    />
                                )}
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </>
    );
}
