import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { guide } from '@/routes';

const sections = [
    { id: 'overview', title: 'How JobProof works' },
    { id: 'setup', title: 'Setting up your company' },
    { id: 'job', title: 'Creating and sending a job' },
    { id: 'milestone', title: 'Running a milestone' },
    { id: 'variation', title: 'Variation orders' },
    { id: 'payments', title: 'Recording payments' },
    { id: 'keeping-track', title: 'Keeping track' },
    { id: 'client', title: 'What your client sees' },
    { id: 'plans', title: 'Plans and limits' },
    { id: 'faq', title: 'Questions' },
];

const milestoneSteps = [
    { status: 'Planned', what: 'Waiting to start.' },
    { status: 'In progress', what: 'You started it and are adding proof.' },
    {
        status: 'Payment requested',
        what: 'The client is reviewing your proof. Proof is locked.',
    },
    {
        status: 'Approved',
        what: 'The client approved. Record the payment when it arrives.',
    },
    {
        status: 'Paid',
        what: 'Payments covering the amount are confirmed by the client.',
    },
];

/**
 * Renders a translated sentence, swapping :b1, :b2... for the given words in bold,
 * so translators can reorder the sentence around them.
 */
function rich(text: string, bold: string[]): ReactNode {
    return text
        .split(/:b(\d+)/)
        .map((part, i) =>
            i % 2 === 1 ? <b key={i}>{bold[Number(part) - 1]}</b> : part,
        );
}

export default function Guide() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('User guide')} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4 pb-16">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold">
                        {t('User guide')}
                    </h1>
                    <p className="text-muted-foreground">
                        {t(
                            'Split each job into paid milestones, prove the work with photos, and get your client’s approval on record before every payment.',
                        )}
                    </p>
                </header>

                <Card className="py-4">
                    <CardContent className="px-4">
                        <p className="mb-2 text-sm font-medium">
                            {t('Contents')}
                        </p>
                        <ol className="grid gap-1 text-sm sm:grid-cols-2">
                            {sections.map((s, i) => (
                                <li key={s.id}>
                                    <a
                                        href={`#${s.id}`}
                                        className="font-medium underline decoration-primary decoration-2 underline-offset-4"
                                    >
                                        {i + 1}. {t(s.title)}
                                    </a>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

                <Section id="overview" title={`1. ${t('How JobProof works')}`}>
                    <p>
                        {t(
                            'Every job has a quote, milestones that add up to it, photo proof per milestone, a client approval per payment request, signed variation orders for extra work, and a payment record. You and your client see the same record, so arguments about work done, extra work or money paid are settled by the record, not by chat history.',
                        )}
                    </p>
                    <p>
                        {t(
                            'Your client never needs an account or an app. They get a link by SMS or WhatsApp and confirm who they are with a 6-digit code sent to their phone.',
                        )}
                    </p>
                    <p>
                        {t(
                            'JobProof does not move money. Clients pay you by transfer, cash or cheque as usual; you record it and the client confirms it, so both sides agree on the balance.',
                        )}
                    </p>
                </Section>

                <Section
                    id="setup"
                    title={`2. ${t('Setting up your company')}`}
                >
                    <Steps>
                        <li>
                            {rich(t('Open :b1 in the sidebar.'), [
                                t('Company'),
                            ])}
                        </li>
                        <li>
                            {t(
                                'Enter your bank, account name and account number. Clients see these on every payment request.',
                            )}
                        </li>
                        <li>
                            {t(
                                'Add your logo. It appears on your client’s pages.',
                            )}
                        </li>
                    </Steps>
                    <p>
                        {rich(
                            t(
                                'Your name, email, password, two-factor login and dark mode are under :b1: click your name at the bottom of the sidebar.',
                            ),
                            [t('Settings')],
                        )}
                    </p>
                </Section>

                <Section
                    id="job"
                    title={`3. ${t('Creating and sending a job')}`}
                >
                    <Steps>
                        <li>
                            {rich(t('Go to :b1 and click :b2.'), [
                                t('Jobs'),
                                t('New job'),
                            ])}
                        </li>
                        <li>
                            {rich(
                                t(
                                    'Pick a client or choose :b1. Use the client’s own mobile number: their login code is sent there. Email is optional; with it, they also get updates by email.',
                                ),
                                [t('+ New client')],
                            )}
                        </li>
                        <li>
                            {t(
                                'Enter the title, site address, quote total in RM, and start and target dates.',
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    'Build the payment schedule. :b1 (House build, Renovation, Installation) splits the quote by percentage, or click :b2 to write your own.',
                                ),
                                [t('Use a template'), t('Add milestone')],
                            )}
                        </li>
                        <li>
                            {t(
                                'Check the total under the schedule. It turns green when the milestones add up to the quote exactly.',
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    'Click :b1, review the job page, then click :b2.',
                                ),
                                [t('Save draft'), t('Send to client')],
                            )}
                        </li>
                    </Steps>
                    <p>
                        {rich(
                            t(
                                'The client gets a text with the link. You can also share it yourself with :b1 or :b2. The job shows :b3 until they accept it, then :b4.',
                            ),
                            [
                                t('Copy client link'),
                                t('WhatsApp'),
                                t('Sent to client'),
                                t('Active'),
                            ],
                        )}
                    </p>
                    <Note>
                        {t(
                            'After a job is sent, the quote and milestone amounts are locked. Change the money only with a variation order. You can still rename a milestone or move its due date.',
                        )}
                    </Note>
                </Section>

                <Section
                    id="milestone"
                    title={`4. ${t('Running a milestone')}`}
                >
                    <p>{t('Each milestone moves through these steps:')}</p>
                    <ol className="space-y-2">
                        {milestoneSteps.map((step, i) => (
                            <li key={step.status} className="flex gap-3">
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                                    {i + 1}
                                </span>
                                <span>
                                    <b>{t(step.status)}:</b> {t(step.what)}
                                </span>
                            </li>
                        ))}
                    </ol>
                    <p>
                        {rich(
                            t(
                                'If the client rejects a request, they must give a reason, and the milestone goes back to :b1 so you can fix the work, add proof and request again.',
                            ),
                            [t('In progress')],
                        )}
                    </p>
                    <Steps>
                        <li>
                            {rich(
                                t(
                                    'Open the milestone from the job page and click :b1.',
                                ),
                                [t('Start milestone')],
                            )}
                        </li>
                        <li>
                            {t(
                                'Upload photos or files, or add a site note. Photos keep the time and location the camera recorded. Every file gets a fingerprint (SHA-256) so any later change to it would show.',
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    'Click :b1, optionally with a note to the client. You need at least one piece of proof.',
                                ),
                                [t('Request payment')],
                            )}
                        </li>
                    </Steps>
                    <p>
                        {rich(
                            t(
                                'While a request is open, its proof is locked. To change it, click :b1, edit the proof, and request again.',
                            ),
                            [t('Withdraw request')],
                        )}
                    </p>
                    <p>
                        {rich(
                            t(
                                ':b1 is for a client who agreed by phone or in person. You must note how and when. The approval is flagged as verbal on both sides and in the PDF, and the client gets a text so they can object.',
                            ),
                            [t('Mark approved verbally')],
                        )}
                    </p>
                </Section>

                <Section id="variation" title={`5. ${t('Variation orders')}`}>
                    <p>
                        {t(
                            'A variation order (VO) changes the job total: extra work adds to it, removed work deducts from it. It counts only after the client signs.',
                        )}
                    </p>
                    <Steps>
                        <li>
                            {rich(
                                t(
                                    'On the job page, click :b1 under Variation orders.',
                                ),
                                [t('New VO')],
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    'Choose :b1 or :b2, describe it, enter the amount, and attach photos or drawings.',
                                ),
                                [t('Extra work'), t('Removed work')],
                            )}
                        </li>
                        <li>
                            {t(
                                'Extra work can add to a milestone or become a new one. A deduction must come off a milestone that hasn’t been requested yet.',
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    'It saves as a draft numbered VO-001, VO-002 and so on. Click :b1 when ready.',
                                ),
                                [t('Send')],
                            )}
                        </li>
                    </Steps>
                    <p>
                        {t(
                            'The client signs with their name and a tick box, and can add a finger signature. Once signed, the milestones and job total update straight away. If they reject it, you see their reason and the total stays the same.',
                        )}
                    </p>
                </Section>

                <Section id="payments" title={`6. ${t('Recording payments')}`}>
                    <Steps>
                        <li>
                            {rich(
                                t(
                                    'When money arrives, click :b1 on the job page.',
                                ),
                                [t('Record payment')],
                            )}
                        </li>
                        <li>
                            {t(
                                'Enter the amount, date received, method (transfer, cash, cheque, other), reference, the milestone it’s for, and a receipt photo if you have one.',
                            )}
                        </li>
                        <li>
                            {t(
                                'The client is asked to confirm it. Part payments are fine.',
                            )}
                        </li>
                    </Steps>
                    <p>
                        {rich(
                            t(
                                ':b1 counts only payments the client confirmed. If the client disputes one, their note shows on the payment so you can sort it out.',
                            ),
                            [t('Balance')],
                        )}
                    </p>
                    <p>
                        {rich(
                            t(
                                'A milestone becomes :b1 when confirmed payments cover its amount. The job becomes :b2 when every milestone is paid and every payment is confirmed.',
                            ),
                            [t('Paid'), t('Completed')],
                        )}
                    </p>
                </Section>

                <Section id="keeping-track" title={`7. ${t('Keeping track')}`}>
                    <ul className="list-disc space-y-1 pl-5">
                        <li>
                            {rich(
                                t(
                                    ':b1 lists approvals, rejections, signatures, confirmations, disputes and client comments. Opening it marks them as read.',
                                ),
                                [t('Notifications')],
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    ':b1 sit on each milestone page and on each variation order. Your client sees and answers them from their link.',
                                ),
                                [t('Comments')],
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    ':b1 you’re told 3 days before a milestone is due. A client who leaves something waiting for 2 days gets a reminder, at most once every 2 days per job.',
                                ),
                                [t('Reminders:')],
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    ':b1 on the job page downloads the full history: milestones, photos with fingerprints, variation orders, payments and every action with who did it and when.',
                                ),
                                [t('PDF record')],
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    ':b1 at the bottom of each job shows the same history. It can’t be edited or deleted.',
                                ),
                                [t('Activity')],
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    ':b1 stops a job for good. Give a reason; the client is told and open requests are withdrawn. The record stays readable for both sides.',
                                ),
                                [t('Cancel job')],
                            )}
                        </li>
                    </ul>
                </Section>

                <Section id="client" title={`8. ${t('What your client sees')}`}>
                    <Steps>
                        <li>
                            {rich(
                                t(
                                    'They open the link, tap :b1 and enter the 6-digit code texted to their phone. That phone stays logged in for 30 days for that job.',
                                ),
                                [t('Send code')],
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    'On their first visit they check the total and schedule and tap :b1.',
                                ),
                                [t('Accept job and schedule')],
                            )}
                        </li>
                        <li>
                            {rich(
                                t(
                                    'For each payment request they see your photos full screen with time and location, plus your bank details, and tap :b1 or :b2 with a reason.',
                                ),
                                [t('Approve'), t('Reject')],
                            )}
                        </li>
                        <li>
                            {t(
                                'They sign or reject variation orders, confirm or dispute payments you recorded, and comment.',
                            )}
                        </li>
                        <li>{t('They can download the same PDF record.')}</li>
                    </Steps>
                </Section>

                <Section id="plans" title={`9. ${t('Plans and limits')}`}>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('Plan')}</TableHead>
                                <TableHead>{t('Price')}</TableHead>
                                <TableHead>{t('Active jobs')}</TableHead>
                                <TableHead>{t('Photo storage')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow>
                                <TableCell>{t('Free')}</TableCell>
                                <TableCell>RM 0</TableCell>
                                <TableCell>1</TableCell>
                                <TableCell>5 GB</TableCell>
                            </TableRow>
                            <TableRow>
                                <TableCell>{t('Solo')}</TableCell>
                                <TableCell>{t('RM 49/month')}</TableCell>
                                <TableCell>10</TableCell>
                                <TableCell>50 GB</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p>
                        {rich(
                            t(
                                'Active jobs are those sent or active; drafts, completed and cancelled jobs don’t count. Your usage and an :b1 button are on the :b2 page. :b3 there lets you change your card or cancel.',
                            ),
                            [
                                t('Upgrade to Solo'),
                                t('Company'),
                                t('Manage billing'),
                            ],
                        )}
                    </p>
                </Section>

                <Section id="faq" title={`10. ${t('Questions')}`}>
                    <Faq q={t('My client didn’t get the code.')}>
                        {rich(
                            t(
                                'Check the mobile number on the job’s client. They can tap :b1; codes expire after 10 minutes, and 3 can be requested every 15 minutes.',
                            ),
                            [t('Send a new code')],
                        )}
                    </Faq>
                    <Faq q={t('I can’t send the job.')}>
                        {t(
                            'The milestones must add up to the quote exactly, and your plan must have room for another active job. The message on the page says which.',
                        )}
                    </Faq>
                    <Faq q={t('I need to change a price after sending.')}>
                        {t(
                            'Use a variation order: extra work adds, removed work deducts. It applies once the client signs.',
                        )}
                    </Faq>
                    <Faq q={t('I uploaded the wrong photo.')}>
                        {t(
                            'Remove it before you request payment. If a request is already open, withdraw it first.',
                        )}
                    </Faq>
                    <Faq q={t('The client says a payment amount is wrong.')}>
                        {t(
                            'They dispute it from their link with a note, which you see on the job and in Notifications. Record a corrected payment for them to confirm.',
                        )}
                    </Faq>
                </Section>
            </div>
        </>
    );
}

function Section({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section id={id} className="scroll-mt-20 space-y-4 leading-relaxed">
            <h2 className="text-lg font-semibold">{title}</h2>
            {children}
        </section>
    );
}

function Steps({ children }: { children: ReactNode }) {
    return <ol className="list-decimal space-y-2 pl-5">{children}</ol>;
}

function Note({ children }: { children: ReactNode }) {
    return (
        <p className="rounded-md border border-status-waiting/40 bg-status-waiting/10 p-3 text-sm">
            {children}
        </p>
    );
}

function Faq({ q, children }: { q: string; children: ReactNode }) {
    return (
        <div>
            <p className="font-medium">{q}</p>
            <p className="text-muted-foreground">{children}</p>
        </div>
    );
}

Guide.layout = {
    breadcrumbs: [{ title: 'User guide', href: guide() }],
};
