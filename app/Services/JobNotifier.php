<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Models\VariationOrder;
use App\Notifications\ClientAlert;
use App\Notifications\ContractorAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Turns audit-trail actions into notifications: the client gets a text (and email) with the link to act on,
 * the contractor gets an in-app notification and a push. Spec events map to actions, e.g. PaymentRequested
 * is `payment_request.sent`. Queued, and only sent once the transaction commits.
 */
class JobNotifier
{
    /**
     * Every message is an English key with placeholders, translated into each recipient's own language when it is
     * sent (Laravel applies their preferred locale per notifiable, queued or not).
     *
     * @param  array<string, mixed>  $meta
     */
    public function notify(string $action, Job $job, User|Client|null $actor, ?Model $subject, array $meta): void
    {
        $base = ['client' => $job->client->name, 'job' => $job->title, 'reason' => (string) ($meta['reason'] ?? $meta['note'] ?? '')];

        match (true) {
            $action === 'job.sent' => $this->toClient($job, 'sent you “:job” to review and accept.', $base, $job->shareUrl()),
            $action === 'job.accepted' => $this->toContractor($job, ':client accepted the job', '“:job” is active. You can start the first milestone.', $base, route('jobs.show', $job)),
            $action === 'job.cancelled' => $this->toClient($job, 'cancelled “:job”. Reason: “:reason”', $base, $job->shareUrl()),
            $action === 'job.completed' => $this->toContractor($job, 'Job completed', 'Every milestone of “:job” is paid and confirmed.', $base, route('jobs.show', $job)),

            $subject instanceof PaymentRequest && $action === 'payment_request.sent' => $this->toClient($job,
                'requests :amount for “:milestone”. Review the proof and approve or reject:',
                $base + $this->money($subject->amount_cents) + ['milestone' => $subject->milestone->name],
                route('client.payment-requests.show', [$job->share_token, $subject->id])),
            $subject instanceof PaymentRequest && $action === 'payment_request.approved' => $this->toContractor($job,
                'Payment request approved', ':client approved :amount for “:milestone”.',
                $base + $this->money($subject->amount_cents) + ['milestone' => $subject->milestone->name], route('milestones.show', $subject->milestone_id)),
            $subject instanceof PaymentRequest && $action === 'payment_request.rejected' => $this->toContractor($job,
                'Payment request rejected', ':client rejected :amount for “:milestone”. Reason: “:reason”',
                $base + $this->money($subject->amount_cents) + ['milestone' => $subject->milestone->name], route('milestones.show', $subject->milestone_id)),
            $subject instanceof PaymentRequest && $action === 'payment_request.approved_verbally' => $this->toClient($job,
                'marked :amount for “:milestone” as approved by you verbally. If that’s wrong, reply with a comment:',
                $base + $this->money($subject->amount_cents) + ['milestone' => $subject->milestone->name],
                route('client.payment-requests.show', [$job->share_token, $subject->id])),

            $subject instanceof VariationOrder && $action === 'variation_order.sent' => $this->toClient($job,
                'sent :number (:amount) for you to sign:',
                $base + ['number' => $subject->number, 'amount' => ($subject->amount_cents < 0 ? '−' : '+').$this->rm(abs($subject->amount_cents))],
                route('client.variation-orders.show', [$job->share_token, $subject->id])),
            $subject instanceof VariationOrder && $action === 'variation_order.signed' => $this->toContractor($job,
                ':number signed', ':client signed :number on “:job”.', $base + ['number' => $subject->number], route('jobs.show', $job)),
            $subject instanceof VariationOrder && $action === 'variation_order.rejected' => $this->toContractor($job,
                ':number rejected', ':client rejected :number on “:job”. Reason: “:reason”', $base + ['number' => $subject->number], route('jobs.show', $job)),

            $subject instanceof Payment && $action === 'payment.recorded' => $this->toClient($job,
                'recorded a payment of :amount. Please confirm you paid it:', $base + $this->money($subject->amount_cents), $job->shareUrl()),
            $subject instanceof Payment && $action === 'payment.confirmed' => $this->toContractor($job,
                'Payment confirmed', ':client confirmed :amount.', $base + $this->money($subject->amount_cents), route('jobs.show', $job)),
            $subject instanceof Payment && $action === 'payment.disputed' => $this->toContractor($job,
                'Payment disputed', ':client disputed :amount. “:reason”', $base + $this->money($subject->amount_cents), route('jobs.show', $job)),

            $subject instanceof Comment && $actor instanceof Client => $this->toContractor($job, 'New comment from :client',
                ':comment', $base + ['comment' => str($subject->body)->limit(140)->toString()], $this->commentUrl($job, $subject)),

            default => null,
        };
    }

    /** Scheduler: 3 days before a milestone's due date. */
    public function milestoneDueSoon(Milestone $milestone): void
    {
        $this->toContractor($milestone->job, 'Milestone due in 3 days', '“:milestone” on “:job” is due :date.',
            ['milestone' => $milestone->name, 'job' => $milestone->job->title, 'date' => (string) $milestone->due_date?->format('d/m/Y')],
            route('milestones.show', $milestone));
    }

    /** Scheduler: the client has had something waiting for 48 hours. */
    public function remindClient(Job $job, int $waiting): void
    {
        $this->toClient($job, $waiting === 1
            ? 'reminder: 1 item is waiting for you on “:job”.'
            : 'reminder: :count items are waiting for you on “:job”.', ['count' => $waiting, 'job' => $job->title], $job->shareUrl());
    }

    /** @param array<string, string|int> $replace */
    private function toClient(Job $job, string $message, array $replace, string $url): void
    {
        $job->client->notify(new ClientAlert($job->company->name, $message, $replace, $url));
    }

    /** @param array<string, string|int> $replace */
    private function toContractor(Job $job, string $title, string $body, array $replace, string $url): void
    {
        Notification::send(
            User::where('company_id', $job->company_id)->where('role', 'owner')->get(),
            new ContractorAlert($title, $body, $replace, $url, $job->id),
        );
    }

    /** @return array{amount: string} */
    private function money(int $cents): array
    {
        return ['amount' => $this->rm($cents)];
    }

    private function commentUrl(Job $job, Comment $comment): string
    {
        return $comment->commentable_type === (new Milestone)->getMorphClass()
            ? route('milestones.show', $comment->commentable_id)
            : route('jobs.show', $job);
    }

    private function rm(int $cents): string
    {
        return 'RM '.number_format($cents / 100, 2);
    }
}
