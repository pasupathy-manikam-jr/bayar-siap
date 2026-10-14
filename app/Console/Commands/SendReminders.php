<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\Milestone;
use App\Services\JobFlow;
use App\Services\JobNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily: warn contractors 3 days before a milestone is due, and nudge clients who have left something
 * waiting for 48 hours (at most once per 48 hours per job, recorded in the audit trail).
 */
#[Signature('jobproof:reminders')]
#[Description('Send milestone-due-soon alerts and 48-hour client reminders')]
class SendReminders extends Command
{
    public const WAITING_HOURS = 48;

    public function handle(JobFlow $flow, JobNotifier $notifier): int
    {
        // ponytail: exact-day match, so a skipped scheduler run skips that day's alerts; track sent alerts if that matters.
        $dueSoon = Milestone::with('job')
            ->whereIn('status', ['planned', 'in_progress'])
            ->whereDate('due_date', today()->addDays(3))
            ->whereHas('job', fn ($q) => $q->where('status', 'active'))
            ->get();
        $dueSoon->each(fn (Milestone $milestone) => $notifier->milestoneDueSoon($milestone));

        $cutoff = now()->subHours(self::WAITING_HOURS);
        $reminded = 0;
        foreach (Job::with('client', 'company')->whereIn('status', ['sent', 'active'])->get() as $job) {
            $waiting = $this->waitingSince($job, $cutoff);
            $recentlyReminded = ActivityLog::where('job_id', $job->id)->where('action', 'client.reminded')->where('created_at', '>', $cutoff)->exists();
            if ($waiting === 0 || $recentlyReminded) {
                continue;
            }
            $flow->log($job, null, 'client.reminded', null, ['waiting' => $waiting]);
            $notifier->remindClient($job, $waiting);
            $reminded++;
        }

        $this->info("{$dueSoon->count()} due-soon alert(s), {$reminded} client reminder(s).");

        return self::SUCCESS;
    }

    /** Items waiting on the client since before the cutoff. */
    private function waitingSince(Job $job, \DateTimeInterface $cutoff): int
    {
        if ($job->status === 'sent') {
            return $job->updated_at <= $cutoff ? 1 : 0;
        }

        return $job->milestones()->whereHas('paymentRequests', fn ($q) => $q->where('status', 'open')->where('created_at', '<=', $cutoff))->count()
            + $job->variationOrders()->where('status', 'sent')->where('updated_at', '<=', $cutoff)->count()
            + $job->payments()->where('client_status', 'pending')->where('created_at', '<=', $cutoff)->count();
    }
}
