<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Job;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Proof;
use App\Models\VariationOrder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // ponytail: summary() is 3 queries per job; fine for a plan capped at 10 active jobs, aggregate in SQL if that grows.
        $openJobs = Job::with('client')->whereIn('status', ['sent', 'active'])->latest('updated_at')->get();

        return Inertia::render('dashboard', [
            'stats' => [
                'active_jobs' => $openJobs->where('status', 'active')->count(),
                'awaiting_acceptance' => $openJobs->where('status', 'sent')->count(),
                'outstanding_cents' => $openJobs->where('status', 'active')->sum(fn (Job $job) => $job->summary()['balance_cents']),
                'awaiting_client' => PaymentRequest::where('status', 'open')->count()
                    + VariationOrder::where('status', 'sent')->count()
                    + Payment::where('client_status', 'pending')->count(),
            ],
            'jobs' => $openJobs->take(6)->map(fn (Job $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'client' => $job->client->name,
                'status' => $job->status,
                'summary' => $job->summary(),
            ])->values(),
            'photos' => Proof::with('milestone.job')->where('type', 'photo')->whereNotNull('path')
                ->latest('captured_at')->limit(8)->get()->map(fn (Proof $p) => $p->toStripItem()),
            'clientActivity' => ActivityLog::with('job:id,title')
                ->where('actor_type', (new Client)->getMorphClass())
                ->latest('id')->limit(8)->get()
                ->map(fn (ActivityLog $entry) => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'label' => $entry->label(),
                    'job_id' => $entry->job_id,
                    'job_title' => $entry->job?->title,
                    'meta' => $entry->meta,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
