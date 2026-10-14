<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Job;
use App\Services\JobFlow;
use App\Services\JobRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class JobController extends Controller
{
    public function __construct(private JobFlow $flow) {}

    /** @return Collection<int, Client> */
    public function clients(): Collection
    {
        return Client::orderBy('name')->get();
    }

    public function storeClient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:30', Rule::unique('clients')->where('company_id', $request->user()->company_id)],
            'email' => 'nullable|email|max:255',
        ]);

        return response()->json(Client::create($data), 201);
    }

    public function templates(): JsonResponse
    {
        return response()->json(config('jobproof.milestone_templates'));
    }

    /** @return array<int, array<string, mixed>> */
    public function index(Request $request): array
    {
        $request->validate(['status' => 'nullable|in:draft,sent,active,completed,cancelled', 'q' => 'nullable|string']);

        return Job::with(['client', 'milestones'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q->where('title', 'like', "%{$term}%")
                ->orWhere('site_address', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$term}%"))))
            ->latest()
            ->get()
            ->map(fn (Job $job) => $job->toArray() + [
                'summary' => $job->summary(),
                'next_milestone' => $job->milestones->whereNotIn('status', ['paid'])->first(),
            ])
            ->values()
            ->all();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required_without:client', 'nullable', Rule::exists('clients', 'id')->where('company_id', $request->user()->company_id)],
            'client' => 'required_without:client_id|array',
            'client.name' => 'required_with:client|string|max:255',
            'client.phone' => 'required_with:client|string|max:30',
            'client.email' => 'nullable|email|max:255',
            'title' => 'required|string|max:255',
            'site_address' => 'required|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'quote_cents' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date|after_or_equal:start_date',
            'template' => ['nullable', Rule::in(array_keys(config('jobproof.milestone_templates')))],
            'milestones' => 'nullable|array',
            ...$this->milestoneRules('milestones.*.'),
        ]);

        if (empty($data['client_id'])) {
            $data['client_id'] = Client::firstOrCreate(
                ['phone' => $data['client']['phone']],
                ['name' => $data['client']['name'], 'email' => $data['client']['email'] ?? null],
            )->id;
        }

        $milestones = $data['milestones']
            ?? (isset($data['template']) ? config("jobproof.milestone_templates.{$data['template']}.milestones") : []);

        $job = $this->flow->createJob($request->user(), Arr::except($data, ['client', 'template', 'milestones']), $milestones);

        return response()->json($this->detail($job), 201);
    }

    /** @return array<string, mixed> */
    public function show(Job $job): array
    {
        return $this->detail($job);
    }

    /** @return array<string, mixed> */
    public function update(Request $request, Job $job): array
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'site_address' => 'sometimes|required|string|max:500',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'quote_cents' => 'sometimes|required|integer|min:1',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
        ]);

        return $this->detail($this->flow->updateJob($job, $request->user(), $data));
    }

    /** @return array<string, mixed> */
    public function send(Request $request, Job $job): array
    {
        $this->flow->sendJob($job, $request->user());

        return $this->detail($job->refresh());
    }

    /** @return array<string, mixed> */
    public function cancel(Request $request, Job $job): array
    {
        $data = $request->validate(['reason' => 'required|string|max:2000']);

        return $this->detail($this->flow->cancelJob($job, $request->user(), $data['reason']));
    }

    public function record(Job $job, JobRecord $record): HttpResponse
    {
        return $record->download($job);
    }

    /** @return LengthAwarePaginator<int, ActivityLog> */
    public function activity(Job $job): LengthAwarePaginator
    {
        return $job->activity()->paginate(50);
    }

    public function storeMilestone(Request $request, Job $job): JsonResponse
    {
        $data = $request->validate($this->milestoneRules());
        $data = $this->flow->splitAmounts($job->quote_cents, [$data])[0];

        return response()->json($this->flow->addMilestone($job, $request->user(), $data), 201);
    }

    /** @return array<string, string> */
    private function milestoneRules(string $prefix = ''): array
    {
        return [
            "{$prefix}name" => 'required|string|max:255',
            "{$prefix}description" => 'nullable|string|max:2000',
            "{$prefix}amount_cents" => "required_without:{$prefix}percent|integer|min:0",
            "{$prefix}percent" => "required_without:{$prefix}amount_cents|numeric|min:0|max:100",
            "{$prefix}due_date" => 'nullable|date',
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Job $job): array
    {
        $job->load(['client', 'milestones' => fn ($q) => $q->withCount('proofs')->with('openRequest'), 'variationOrders', 'payments']);

        return $job->toArray() + ['summary' => $job->summary(), 'share_url' => $job->status === 'draft' ? null : $job->shareUrl()];
    }
}
