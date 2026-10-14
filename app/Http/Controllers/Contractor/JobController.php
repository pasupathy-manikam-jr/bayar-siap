<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contractor\JobRequest;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\Proof;
use App\Models\VariationOrder;
use App\Services\JobFlow;
use App\Services\JobRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class JobController extends Controller
{
    public function __construct(private JobFlow $flow) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,sent,active,completed,cancelled'],
        ]);

        $jobs = Job::with(['client', 'milestones'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$term}%")
                ->orWhere('site_address', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$term}%"))))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Job $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'client' => $job->client->name,
                'site_address' => $job->site_address,
                'status' => $job->status,
                'summary' => $job->summary(),
                'next_milestone' => $job->milestones->firstWhere(fn (Milestone $m) => $m->status !== 'paid')?->only(['name', 'due_date', 'status']),
                'updated_at' => $job->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('jobs/index', ['jobs' => $jobs, 'filters' => $filters]);
    }

    public function create(): Response
    {
        return Inertia::render('jobs/form', $this->formProps());
    }

    public function store(JobRequest $request): RedirectResponse
    {
        $job = $this->flow->createJob($request->user(), $request->jobData($this->resolveClient($request)), $request->milestoneRows());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved. Send it to the client when ready.')]);

        return to_route('jobs.show', $job);
    }

    public function show(Job $job): Response
    {
        $job->load(['client', 'milestones' => fn ($q) => $q->withCount('proofs')->with('openRequest'), 'variationOrders.comments.author', 'payments.milestone']);

        return Inertia::render('jobs/show', [
            'job' => [
                ...$job->only(['id', 'title', 'site_address', 'status']),
                'start_date' => $job->start_date?->toDateString(),
                'target_date' => $job->target_date?->toDateString(),
                'accepted_at' => $job->accepted_at?->toIso8601String(),
                'client' => $job->client->only(['id', 'name', 'phone', 'email']),
                'share_url' => in_array($job->status, ['sent', 'active', 'completed'], true) ? $job->shareUrl() : null,
                'cancel_reason' => $job->cancelReason(),
            ],
            'summary' => $job->summary(),
            'milestones' => $job->milestones->map(fn (Milestone $m) => [
                ...$m->only(['id', 'sequence', 'name', 'amount_cents', 'status', 'proofs_count', 'variation_order_id']),
                'due_date' => $m->due_date?->toDateString(),
                'open_request_id' => $m->openRequest?->id,
            ]),
            'variationOrders' => $job->variationOrders->sortByDesc('id')->values()->map(fn (VariationOrder $vo) => [
                ...$vo->only(['id', 'number', 'description', 'reason', 'amount_cents', 'status', 'signer_name', 'reject_reason']),
                'signed_at' => $vo->signed_at?->toIso8601String(),
                'comments' => $vo->comments->sortBy('id')->values()->map(fn (Comment $c) => $c->toThread()),
            ]),
            'payments' => $job->payments->sortByDesc('paid_on')->values()->map(fn (Payment $p) => [
                ...$p->only(['id', 'amount_cents', 'method', 'reference', 'client_status', 'dispute_note']),
                'paid_on' => $p->paid_on->toDateString(),
                'milestone' => $p->milestone?->name,
            ]),
            'photos' => Proof::with('milestone.job')->where('type', 'photo')->whereNotNull('path')
                ->whereIn('milestone_id', $job->milestones->pluck('id'))
                ->latest('captured_at')->limit(12)->get()->map(fn (Proof $p) => $p->toStripItem()),
            'activity' => $job->activity()->limit(30)->get()->map(fn (ActivityLog $entry) => [
                'id' => $entry->id,
                'action' => $entry->action,
                'label' => $entry->label(),
                'actor' => match ($entry->actor_type) {
                    (new Client)->getMorphClass() => 'client',
                    null => 'system',
                    default => 'contractor',
                },
                'meta' => $entry->meta,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function edit(Job $job): Response|RedirectResponse
    {
        if ($job->status !== 'draft') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Only draft jobs can be edited. Use a variation order instead.')]);

            return to_route('jobs.show', $job);
        }

        $job->load(['client', 'milestones']);

        return Inertia::render('jobs/form', [
            ...$this->formProps(),
            'job' => [
                'id' => $job->id,
                'client_id' => $job->client_id,
                'title' => $job->title,
                'site_address' => $job->site_address,
                'quote' => number_format($job->quote_cents / 100, 2, '.', ''),
                'start_date' => $job->start_date?->toDateString(),
                'target_date' => $job->target_date?->toDateString(),
                'milestones' => $job->milestones->map(fn (Milestone $m) => [
                    'name' => $m->name,
                    'amount' => number_format($m->amount_cents / 100, 2, '.', ''),
                    'due_date' => $m->due_date?->toDateString(),
                ]),
            ],
        ]);
    }

    public function update(JobRequest $request, Job $job): RedirectResponse
    {
        $this->flow->updateDraft($job, $request->user(), $request->jobData($this->resolveClient($request)), $request->milestoneRows());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft updated.')]);

        return to_route('jobs.show', $job);
    }

    public function send(Request $request, Job $job): RedirectResponse
    {
        $wasDraft = $job->status === 'draft';
        $this->flow->sendJob($job, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => $wasDraft
            ? __('Job sent. Share the link with your client.')
            : __('Link is ready to share again.')]);

        return to_route('jobs.show', $job);
    }

    public function cancel(Request $request, Job $job): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->flow->cancelJob($job, $request->user(), $data['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job cancelled.')]);

        return to_route('jobs.show', $job);
    }

    public function record(Job $job, JobRecord $record): HttpResponse
    {
        return $record->download($job);
    }

    /** @return array<string, mixed> */
    private function formProps(): array
    {
        return [
            'clients' => Client::orderBy('name')->get(['id', 'name', 'phone']),
            'templates' => config('jobproof.milestone_templates'),
        ];
    }

    private function resolveClient(JobRequest $request): int
    {
        if ($request->filled('client_id')) {
            return $request->integer('client_id');
        }

        return Client::firstOrCreate(
            ['phone' => $request->string('client_phone')->trim()->toString()],
            ['name' => $request->string('client_name')->toString(), 'email' => $request->input('client_email')],
        )->id;
    }
}
