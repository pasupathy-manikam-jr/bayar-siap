<?php

namespace App\Http\Controllers\ClientLink;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\Comment;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Proof;
use App\Models\VariationOrder;
use App\Models\VoAttachment;
use App\Services\JobFlow;
use App\Services\JobRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Everything the client does from the job link: view, accept, approve or reject, sign, confirm payments. */
class JobController extends Controller
{
    public function __construct(private JobFlow $flow) {}

    public function show(Request $request): Response
    {
        $job = $this->job($request);

        if (! $request->attributes->get('clientSession')) {
            return Inertia::render('client/verify', [
                'job' => ['title' => $job->title, 'share_token' => $job->share_token],
                'contractor' => $job->company->name,
                'maskedPhone' => $this->maskPhone($job->client->phone),
                'codeSent' => $request->session()->has("client_otp.{$job->id}"),
            ]);
        }

        $job->load(['milestones.openRequest', 'payments.milestone']);

        return Inertia::render('client/show', [
            'job' => $this->jobProps($job),
            'contractor' => $this->contractorProps($job),
            'summary' => $job->summary(),
            'milestones' => $job->milestones->map(fn (Milestone $m) => [
                'id' => $m->id,
                'sequence' => $m->sequence,
                'name' => $m->name,
                'amount_cents' => $m->amount_cents,
                'due_date' => $m->due_date?->toDateString(),
                'status' => $m->status,
                'open_request_id' => $m->openRequest?->id,
            ]),
            'variationOrders' => $job->variationOrders()->where('status', '!=', 'draft')->latest('id')->get()
                ->map(fn (VariationOrder $vo) => $vo->only(['id', 'number', 'description', 'amount_cents', 'status'])),
            'payments' => $job->payments->sortByDesc('paid_on')->values()->map(fn (Payment $p) => [
                'id' => $p->id,
                'amount_cents' => $p->amount_cents,
                'paid_on' => $p->paid_on->toDateString(),
                'method' => $p->method,
                'reference' => $p->reference,
                'client_status' => $p->client_status,
                'milestone' => $p->milestone?->name,
            ]),
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $job = $this->job($request);
        $this->flow->acceptJob($job, $this->client($request));

        return $this->back($job, __('Job accepted. Your contractor has been notified.'));
    }

    public function showPaymentRequest(Request $request, string $paymentRequest): Response
    {
        $job = $this->job($request);
        $pr = $this->findPaymentRequest($job, $paymentRequest);

        return Inertia::render('client/payment-request', [
            'job' => $this->jobProps($job),
            'contractor' => $this->contractorProps($job),
            'paymentRequest' => [
                ...$pr->only(['id', 'amount_cents', 'note', 'status', 'reject_reason', 'verbal_approval_note']),
                'requested_at' => $pr->created_at?->toIso8601String(),
                'decided_at' => $pr->decided_at?->toIso8601String(),
            ],
            'milestone' => $pr->milestone->only(['id', 'name', 'description', 'sequence']),
            'comments' => $pr->milestone->comments()->with('author')->oldest('id')->get()->map(fn (Comment $c) => $c->toThread()),
            'proofs' => $pr->milestone->proofs()->oldest('captured_at')->get()->map(fn (Proof $p) => [
                ...$p->only(['id', 'type', 'caption', 'lat', 'lng', 'sha256']),
                'captured_at' => $p->captured_at?->toIso8601String(),
                'url' => $p->path ? Storage::temporaryUrl($p->path, now()->addHour()) : null,
                'thumb_url' => ($p->thumb_path ?? $p->path) ? Storage::temporaryUrl((string) ($p->thumb_path ?? $p->path), now()->addHour()) : null,
            ]),
        ]);
    }

    public function approve(Request $request, string $paymentRequest): RedirectResponse
    {
        $job = $this->job($request);
        $this->flow->approveRequest($this->findPaymentRequest($job, $paymentRequest), $this->client($request));

        return $this->back($job, __('Payment request approved.'));
    }

    public function reject(Request $request, string $paymentRequest): RedirectResponse
    {
        $job = $this->job($request);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->flow->rejectRequest($this->findPaymentRequest($job, $paymentRequest), $this->client($request), $data['reason']);

        return $this->back($job, __('Payment request rejected. Your contractor will see your reason.'));
    }

    public function showVariationOrder(Request $request, string $variationOrder): Response
    {
        $job = $this->job($request);
        $vo = $this->findVariationOrder($job, $variationOrder);

        return Inertia::render('client/variation-order', [
            'job' => $this->jobProps($job),
            'contractor' => $this->contractorProps($job),
            'variationOrder' => [
                ...$vo->only(['id', 'number', 'description', 'reason', 'amount_cents', 'status', 'signer_name', 'reject_reason']),
                'signed_at' => $vo->signed_at?->toIso8601String(),
                'target_milestone' => $vo->milestone_id ? $job->milestones()->whereKey($vo->milestone_id)->value('name') : null,
            ],
            'comments' => $vo->comments()->with('author')->oldest('id')->get()->map(fn (Comment $c) => $c->toThread()),
            'attachments' => $vo->attachments->map(fn (VoAttachment $a) => [
                'id' => $a->id,
                'url' => Storage::temporaryUrl($a->path, now()->addHour()),
                'is_pdf' => str_ends_with(strtolower($a->path), '.pdf'),
            ]),
        ]);
    }

    public function sign(Request $request, string $variationOrder): RedirectResponse
    {
        $job = $this->job($request);
        $vo = $this->findVariationOrder($job, $variationOrder);
        $data = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'agree' => ['accepted'],
            'signature' => ['nullable', 'string', 'max:700000'],
        ], [
            'agree.accepted' => __('Tick the box to confirm you agree to this variation.'),
        ]);

        $signaturePath = null;
        if (! empty($data['signature'])) {
            $png = $this->decodeSignature($data['signature']);
            $signaturePath = "signatures/{$job->company_id}/vo-{$vo->id}.png";
            Storage::put($signaturePath, $png);
        }

        $this->flow->signVariationOrder($vo, $this->client($request), $data['signer_name'], $signaturePath);

        return $this->back($job, __(':number signed. The job total has been updated.', ['number' => $vo->number]));
    }

    public function rejectVariationOrder(Request $request, string $variationOrder): RedirectResponse
    {
        $job = $this->job($request);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $vo = $this->findVariationOrder($job, $variationOrder);
        $this->flow->rejectVariationOrder($vo, $this->client($request), $data['reason']);

        return $this->back($job, __(':number rejected.', ['number' => $vo->number]));
    }

    public function confirmPayment(Request $request, string $payment): RedirectResponse
    {
        $job = $this->job($request);
        $this->flow->confirmPayment($job->payments()->findOrFail($payment), $this->client($request));

        return $this->back($job, __('Payment confirmed.'));
    }

    public function disputePayment(Request $request, string $payment): RedirectResponse
    {
        $job = $this->job($request);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->flow->disputePayment($job->payments()->findOrFail($payment), $this->client($request), $data['note']);

        return $this->back($job, __('Payment disputed. Your contractor will see your note.'));
    }

    public function comment(Request $request): RedirectResponse
    {
        $job = $this->job($request);
        $data = $request->validate([
            'commentable_type' => ['required', 'in:milestone,variation_order'],
            'commentable_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], ['body' => __('comment')]);

        $item = $data['commentable_type'] === 'milestone'
            ? $job->milestones()->findOrFail((int) $data['commentable_id'])
            : $this->findVariationOrder($job, (string) $data['commentable_id']);
        $this->flow->comment($item, $this->client($request), $data['body']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment sent to your contractor.')]);

        return back();
    }

    public function record(Request $request, JobRecord $record): HttpResponse
    {
        return $record->download($this->job($request));
    }

    private function job(Request $request): Job
    {
        return $request->attributes->get('job');
    }

    private function client(Request $request): Client
    {
        /** @var ClientSession $session */
        $session = $request->attributes->get('clientSession');

        return $session->client;
    }

    private function findPaymentRequest(Job $job, string $id): PaymentRequest
    {
        return PaymentRequest::with('milestone')
            ->whereHas('milestone', fn ($q) => $q->where('job_id', $job->id))
            ->findOrFail($id);
    }

    private function findVariationOrder(Job $job, string $id): VariationOrder
    {
        return $job->variationOrders()->where('status', '!=', 'draft')->with('attachments')->findOrFail($id);
    }

    /** Accepts only a reasonably sized PNG data URL from the signature pad. */
    private function decodeSignature(string $dataUrl): string
    {
        $prefix = 'data:image/png;base64,';
        $png = str_starts_with($dataUrl, $prefix) ? base64_decode(substr($dataUrl, strlen($prefix)), true) : false;
        $info = $png === false ? false : @getimagesizefromstring($png);

        if ($png === false || $info === false || $info[2] !== IMAGETYPE_PNG) {
            throw ValidationException::withMessages(['signature' => __('The signature could not be read. Clear it and sign again.')]);
        }

        return $png;
    }

    private function back(Job $job, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('client.show', $job->share_token);
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return str_repeat('•', max(strlen($digits) - 4, 0)).substr($digits, -4);
    }

    /** @return array<string, mixed> */
    private function jobProps(Job $job): array
    {
        return [
            ...$job->only(['id', 'title', 'site_address', 'status', 'quote_cents', 'share_token']),
            'start_date' => $job->start_date?->toDateString(),
            'target_date' => $job->target_date?->toDateString(),
            'accepted_at' => $job->accepted_at?->toIso8601String(),
            'client_name' => $job->client->name,
            'cancel_reason' => $job->cancelReason(),
        ];
    }

    /** @return array<string, string|null> */
    private function contractorProps(Job $job): array
    {
        $company = $job->company;

        return [
            'name' => $company->name,
            'logo_url' => $company->logo_path ? Storage::temporaryUrl($company->logo_path, now()->addHour()) : null,
            'bank_name' => $company->bank_name,
            'bank_account_name' => $company->bank_account_name,
            'bank_account_no' => $company->bank_account_no,
        ];
    }
}
