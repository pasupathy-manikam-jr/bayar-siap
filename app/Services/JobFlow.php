<?php

namespace App\Services;

use App\Jobs\MakeProofThumbnail;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Proof;
use App\Models\User;
use App\Models\VariationOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Every job state transition and the spec's key invariants live here, so the contractor API
 * and the client link cannot disagree about what is allowed.
 */
class JobFlow
{
    public function __construct(private JobNotifier $notifier) {}

    // ---- Jobs & milestones -------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $milestones
     */
    public function createJob(User $user, array $data, array $milestones): Job
    {
        return DB::transaction(function () use ($user, $data, $milestones) {
            $job = Job::create($data);
            foreach ($this->splitAmounts($job->quote_cents, $milestones) as $i => $m) {
                $job->milestones()->create($m + ['sequence' => $i + 1]);
            }
            $this->log($job, $user, 'job.created', $job);

            return $job;
        });
    }

    /**
     * Resolve percent milestones to cents; rounding remainder goes to the last percent milestone.
     *
     * @param  array<int, array<string, mixed>>  $milestones
     * @return list<array<string, mixed>>
     */
    public function splitAmounts(int $quoteCents, array $milestones): array
    {
        $lastPercent = null;
        foreach ($milestones as $i => $m) {
            if (isset($m['percent'])) {
                $milestones[$i]['amount_cents'] = intdiv((int) round($quoteCents * $m['percent'] * 100), 10000);
                $lastPercent = $i;
            }
            unset($milestones[$i]['percent']);
        }
        if ($lastPercent !== null) {
            $diff = $quoteCents - array_sum(array_column($milestones, 'amount_cents'));
            // Only absorb rounding dust; a real mismatch is left for the sum check to report.
            if (abs($diff) < count($milestones)) {
                $milestones[$lastPercent]['amount_cents'] += $diff;
            }
        }

        return array_values($milestones);
    }

    /**
     * Replace a draft's details and milestone schedule in one go (the web job form).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $milestones
     */
    public function updateDraft(Job $job, User $user, array $data, array $milestones): Job
    {
        return DB::transaction(function () use ($job, $user, $data, $milestones) {
            $job = $this->lock($job);
            $this->ensure($job->status === 'draft', 'Only draft jobs can be edited.');
            $job->update($data);
            $job->milestones()->delete();
            foreach ($milestones as $i => $m) {
                $job->milestones()->create($m + ['sequence' => $i + 1]);
            }
            $this->log($job, $user, 'job.updated', $job);

            return $job;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateJob(Job $job, User $user, array $data): Job
    {
        $this->ensure($job->status === 'draft', 'Only draft jobs can be edited.');
        $job->update($data);
        $this->log($job, $user, 'job.updated', $job, $job->getChanges());

        return $job;
    }

    /** @param array<string, mixed> $data */
    public function addMilestone(Job $job, User $user, array $data): Milestone
    {
        // After sending, money only changes through signed variation orders.
        $this->ensure($job->status === 'draft', 'Milestones can only be added to a draft job; use a variation order.');
        $milestone = $job->milestones()->create($data + ['sequence' => (int) $job->milestones()->max('sequence') + 1]);
        $this->log($job, $user, 'milestone.added', $milestone);

        return $milestone;
    }

    /** @param array<string, mixed> $data */
    public function updateMilestone(Milestone $milestone, User $user, array $data): Milestone
    {
        $this->ensure(in_array($milestone->status, ['planned', 'in_progress'], true), 'Milestone can no longer be edited.');
        if (array_key_exists('amount_cents', $data) && $data['amount_cents'] !== $milestone->amount_cents) {
            $this->ensure($milestone->job->status === 'draft', 'Amounts are locked once the job is sent; use a variation order.');
        }
        $milestone->update($data);
        $this->log($milestone->job, $user, 'milestone.updated', $milestone, $milestone->getChanges());

        return $milestone;
    }

    public function sendJob(Job $job, User $user): Job
    {
        return DB::transaction(function () use ($job, $user) {
            $job = $this->lock($job);
            $this->ensure(in_array($job->status, ['draft', 'sent'], true), 'Job has already been accepted.');
            $this->ensure($job->milestones()->exists(), 'Add at least one milestone before sending.');
            $this->assertMilestonesSumToTotal($job);

            if ($job->status === 'draft') {
                $limit = $user->company->activeJobLimit();
                $open = Job::whereIn('status', ['sent', 'active'])->count();
                $this->ensure($limit === null || $open < $limit, 'Your plan allows :limit active job(s). Upgrade to add more.', ['limit' => (int) $limit]);
                $job->status = 'sent';
                $job->save();
            }
            $this->log($job, $user, 'job.sent', $job);

            return $job;
        });
    }

    /**
     * Stop a job for good. Open payment requests are withdrawn; the record stays readable for both sides and
     * the client can still confirm or dispute payments already recorded.
     */
    public function cancelJob(Job $job, User $user, string $reason): Job
    {
        $this->ensure(trim($reason) !== '', 'Give a reason for cancelling.');

        return DB::transaction(function () use ($job, $user, $reason) {
            $job = $this->lock($job);
            $this->ensure(in_array($job->status, ['draft', 'sent', 'active'], true), 'Only draft, sent or active jobs can be cancelled.');

            PaymentRequest::withoutGlobalScopes()->where('status', 'open')
                ->whereIn('milestone_id', $job->milestones()->select('id'))
                ->update(['status' => 'withdrawn', 'decided_at' => now()]);
            $job->milestones()->where('status', 'requested')->update(['status' => 'in_progress']);

            $wasDraft = $job->status === 'draft';
            $job->update(['status' => 'cancelled']);
            // A draft was never shared, so there is nobody to tell.
            $this->log($job, $user, $wasDraft ? 'job.cancelled_draft' : 'job.cancelled', $job, ['reason' => trim($reason)]);

            return $job;
        });
    }

    public function acceptJob(Job $job, Client $client): Job
    {
        return DB::transaction(function () use ($job, $client) {
            $job = $this->lock($job);
            $this->ensure($job->status === 'sent', 'This job is not waiting for acceptance.');
            $job->update(['status' => 'active', 'accepted_at' => now()]);
            $this->log($job, $client, 'job.accepted', $job);

            return $job;
        });
    }

    public function startMilestone(Milestone $milestone, User $user): Milestone
    {
        $this->ensure($milestone->job->status === 'active', 'The client must accept the job first.');
        $this->ensure($milestone->status === 'planned', 'Milestone already started.');
        $milestone->update(['status' => 'in_progress']);
        $this->log($milestone->job, $user, 'milestone.started', $milestone);

        return $milestone;
    }

    // ---- Proofs ------------------------------------------------------------

    /** @param array<string, mixed> $data */
    public function addProof(Milestone $milestone, User $user, array $data): Proof
    {
        return DB::transaction(function () use ($milestone, $user, $data) {
            $milestone = $this->lock($milestone);
            $this->ensure($milestone->job->status === 'active', 'Proof can only be added to an active job.');
            $this->ensure(! $milestone->proofsLocked(), 'Proof is locked while a payment request is open or after approval.');
            // Measured from storage, not trusted from the caller.
            $data['bytes'] = isset($data['path']) && Storage::exists((string) $data['path']) ? Storage::size((string) $data['path']) : 0;
            $this->ensureStorage($milestone->job->company, $data['bytes']);
            $proof = $milestone->proofs()->create($data + ['uploaded_by' => $user->id]);
            $this->log($milestone->job, $user, 'proof.added', $proof, ['sha256' => $proof->sha256]);
            if ($proof->type === 'photo' && $proof->path) {
                MakeProofThumbnail::dispatch($proof)->afterCommit();
            }

            return $proof;
        });
    }

    /** Removed proof is not evidence any more: its files go too (the audit log keeps the hash). */
    public function deleteProof(Proof $proof, User $user): void
    {
        DB::transaction(function () use ($proof, $user) {
            $milestone = $this->lock($proof->milestone);
            $this->ensure(! $milestone->proofsLocked(), 'Proof is locked while a payment request is open or after approval.');
            $proof->delete();
            $this->log($milestone->job, $user, 'proof.deleted', $proof, ['sha256' => $proof->sha256]);
            DB::afterCommit(fn () => Storage::delete(array_filter([$proof->path, $proof->thumb_path])));
        });
    }

    // ---- Payment requests --------------------------------------------------

    public function requestPayment(Milestone $milestone, User $user, ?string $note): PaymentRequest
    {
        return DB::transaction(function () use ($milestone, $user, $note) {
            $milestone = $this->lock($milestone);
            $this->ensure($milestone->job->status === 'active', 'Payment can only be requested on an active job.');
            $this->ensure($milestone->status === 'in_progress', 'Only an in-progress milestone can request payment.');
            $this->ensure($milestone->proofs()->exists(), 'Add at least one proof before requesting payment.');
            $this->ensure(! $milestone->openRequest()->exists(), 'This milestone already has an open request.');

            $request = $milestone->paymentRequests()->create(['amount_cents' => $milestone->amount_cents, 'note' => $note]);
            $milestone->update(['status' => 'requested']);
            $this->log($milestone->job, $user, 'payment_request.sent', $request);

            return $request;
        });
    }

    public function withdrawRequest(PaymentRequest $request, User $user): PaymentRequest
    {
        return $this->closeRequest($request, $user, 'withdrawn');
    }

    public function approveRequest(PaymentRequest $request, Client $client): PaymentRequest
    {
        return $this->closeRequest($request, $client, 'approved');
    }

    public function rejectRequest(PaymentRequest $request, Client $client, string $reason): PaymentRequest
    {
        $this->ensure(trim($reason) !== '', 'A reason is required to reject.');

        return $this->closeRequest($request, $client, 'rejected', $reason);
    }

    /**
     * The client agreed outside the app (phone, in person). Counts as approved, but is flagged as verbal on
     * every screen and in the PDF, and the client is told so they can object.
     */
    public function approveVerbally(PaymentRequest $request, User $user, string $note): PaymentRequest
    {
        $this->ensure(trim($note) !== '', 'Note how and when the client approved.');

        return $this->closeRequest($request, $user, 'approved', verbalNote: trim($note));
    }

    private function closeRequest(PaymentRequest $request, User|Client $actor, string $status, ?string $reason = null, ?string $verbalNote = null): PaymentRequest
    {
        return DB::transaction(function () use ($request, $actor, $status, $reason, $verbalNote) {
            $milestone = $this->lock($request->milestone);
            $request = $this->lock($request);
            $this->ensure($request->status === 'open', 'This request is no longer open.');

            $request->update([
                'status' => $status,
                'decided_at' => now(),
                'decided_by_client_id' => $actor instanceof Client ? $actor->id : null,
                'reject_reason' => $reason,
                'verbal_approval_note' => $verbalNote,
            ]);
            // Rejected or withdrawn goes back to in progress so proof can be fixed and re-requested.
            $milestone->update(['status' => $status === 'approved' ? 'approved' : 'in_progress']);
            $action = $verbalNote ? 'payment_request.approved_verbally' : "payment_request.{$status}";
            $this->log($milestone->job, $actor, $action, $request, array_filter(['reason' => $reason, 'note' => $verbalNote]));

            if ($status === 'approved') {
                $this->settle($milestone->job);
            }

            return $request;
        });
    }

    // ---- Variation orders --------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments  photos/drawings; over the plan's storage nothing is created
     */
    public function createVariationOrder(Job $job, User $user, array $data, array $attachments = []): VariationOrder
    {
        return DB::transaction(function () use ($job, $user, $data, $attachments) {
            $job = $this->lock($job);
            $this->ensure(in_array($job->status, ['sent', 'active'], true), 'Variation orders apply to sent or active jobs.');
            $this->checkVoTarget($job, $data['milestone_id'] ?? null, (int) $data['amount_cents']);

            $number = sprintf('VO-%03d', $job->variationOrders()->count() + 1);
            $vo = $job->variationOrders()->create($data + ['number' => $number]);
            $this->attachToVariationOrder($vo, $attachments);
            $this->log($job, $user, 'variation_order.created', $vo);

            return $vo;
        });
    }

    /** @param list<UploadedFile> $files */
    private function attachToVariationOrder(VariationOrder $vo, array $files): void
    {
        $this->ensureStorage($vo->job->company, array_sum(array_map(fn (UploadedFile $f) => (int) $f->getSize(), $files)));
        foreach ($files as $file) {
            $vo->attachments()->create([
                'path' => $file->store("vo/{$vo->job->company_id}/{$vo->id}"),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'bytes' => (int) $file->getSize(),
            ]);
        }
    }

    public function sendVariationOrder(VariationOrder $vo, User $user): VariationOrder
    {
        $this->ensure($vo->status === 'draft', 'Variation order already sent.');
        $this->ensure(in_array($vo->job->status, ['sent', 'active'], true), 'This job is no longer open for variations.');
        $vo->update(['status' => 'sent']);
        $this->log($vo->job, $user, 'variation_order.sent', $vo);

        return $vo;
    }

    public function signVariationOrder(VariationOrder $vo, Client $client, string $signerName, ?string $signaturePath): VariationOrder
    {
        return DB::transaction(function () use ($vo, $client, $signerName, $signaturePath) {
            $job = $this->lock($vo->job);
            $vo = $this->lock($vo);
            $this->ensure($vo->status === 'sent', 'This variation order is not awaiting signature.');
            $this->ensure($job->status === 'active', 'Accept the job before signing variation orders.');
            $this->checkVoTarget($job, $vo->milestone_id, $vo->amount_cents);

            if ($vo->milestone_id) {
                $milestone = $this->lock($job->milestones()->findOrFail($vo->milestone_id));
                $milestone->update(['amount_cents' => $milestone->amount_cents + $vo->amount_cents]);
            } else {
                $job->milestones()->create([
                    'variation_order_id' => $vo->id,
                    'sequence' => (int) $job->milestones()->max('sequence') + 1,
                    'name' => "{$vo->number}: ".str($vo->description)->limit(80),
                    'description' => $vo->description,
                    'amount_cents' => $vo->amount_cents,
                ]);
            }

            $vo->update(['status' => 'signed', 'signed_at' => now(), 'signer_name' => $signerName, 'signature_path' => $signaturePath]);
            $this->assertMilestonesSumToTotal($job);
            $this->log($job, $client, 'variation_order.signed', $vo, ['signer_name' => $signerName]);

            return $vo;
        });
    }

    public function rejectVariationOrder(VariationOrder $vo, Client $client, string $reason): VariationOrder
    {
        $this->ensure(trim($reason) !== '', 'A reason is required to reject.');
        $this->ensure($vo->status === 'sent', 'This variation order is not awaiting signature.');
        $this->ensure($vo->job->status !== 'cancelled', 'This job has been cancelled.');
        $vo->update(['status' => 'rejected', 'reject_reason' => $reason]);
        $this->log($vo->job, $client, 'variation_order.rejected', $vo, ['reason' => $reason]);

        return $vo;
    }

    /** A VO either adds a new (positive) milestone or adjusts one not yet requested, never below zero. */
    private function checkVoTarget(Job $job, ?int $milestoneId, int $amountCents): void
    {
        $this->ensure($amountCents !== 0, 'Variation amount cannot be zero.');
        if (! $milestoneId) {
            $this->ensure($amountCents > 0, 'A deduction must target an existing milestone.');

            return;
        }
        $milestone = $job->milestones()->find($milestoneId);
        $this->ensure($milestone !== null, 'Milestone does not belong to this job.');
        $this->ensure(in_array($milestone->status, ['planned', 'in_progress'], true), 'Only a milestone not yet requested can be adjusted.');
        $this->ensure($milestone->amount_cents + $amountCents >= 0, 'Deduction is larger than the milestone amount.');
    }

    // ---- Payments ----------------------------------------------------------

    /** @param array<string, mixed> $data */
    public function recordPayment(Job $job, User $user, array $data): Payment
    {
        $this->ensure($job->status === 'active', 'Payments can only be recorded on an active job.');
        if (! empty($data['milestone_id'])) {
            $this->ensure($job->milestones()->whereKey($data['milestone_id'])->exists(), 'Milestone does not belong to this job.');
        }
        $payment = $job->payments()->create($data);
        $this->log($job, $user, 'payment.recorded', $payment, ['amount_cents' => $payment->amount_cents]);

        return $payment;
    }

    public function confirmPayment(Payment $payment, Client $client): Payment
    {
        return DB::transaction(function () use ($payment, $client) {
            $payment = $this->lock($payment);
            $this->ensure($payment->client_status !== 'confirmed', 'Payment already confirmed.');
            $payment->update(['client_status' => 'confirmed', 'confirmed_at' => now()]);
            $this->log($payment->job, $client, 'payment.confirmed', $payment);
            $this->settle($payment->job);

            return $payment;
        });
    }

    public function disputePayment(Payment $payment, Client $client, string $note): Payment
    {
        $this->ensure(trim($note) !== '', 'Tell the contractor what is wrong.');
        $this->ensure($payment->client_status === 'pending', 'Only a pending payment can be disputed.');
        $payment->update(['client_status' => 'disputed', 'dispute_note' => $note]);
        $this->log($payment->job, $client, 'payment.disputed', $payment, ['note' => $note]);

        return $payment;
    }

    /**
     * Approved milestones whose confirmed payments cover their amount become paid; the job completes
     * when every milestone is paid and every payment is confirmed.
     */
    public function settle(Job $job): void
    {
        $confirmedByMilestone = $job->payments()->where('client_status', 'confirmed')->whereNotNull('milestone_id')
            ->groupBy('milestone_id')->selectRaw('milestone_id, sum(amount_cents) as total')->pluck('total', 'milestone_id');

        foreach ($job->milestones()->where('status', 'approved')->get() as $milestone) {
            if ((int) ($confirmedByMilestone[$milestone->id] ?? 0) >= $milestone->amount_cents) {
                $milestone->update(['status' => 'paid']);
                $this->log($job, null, 'milestone.paid', $milestone);
            }
        }

        $allPaid = ! $job->milestones()->where('status', '!=', 'paid')->exists();
        $allConfirmed = ! $job->payments()->where('client_status', '!=', 'confirmed')->exists();
        if ($job->status === 'active' && $allPaid && $allConfirmed) {
            $job->update(['status' => 'completed']);
            $this->log($job, null, 'job.completed', $job);
        }
    }

    // ---- Shared ------------------------------------------------------------

    public function assertMilestonesSumToTotal(Job $job): void
    {
        $sum = (int) $job->milestones()->sum('amount_cents');
        $total = $job->totalCents();
        $this->ensure($sum === $total, 'Milestones add up to RM :sum but the job total is RM :total.', [
            'sum' => number_format($sum / 100, 2), 'total' => number_format($total / 100, 2),
        ]);
    }

    /** Either side comments on a milestone, payment request or variation order; it lands in the audit trail too. */
    public function comment(Milestone|PaymentRequest|VariationOrder $item, User|Client $actor, string $body): Comment
    {
        $this->ensure(trim($body) !== '', 'Write a comment first.');
        $job = $item instanceof PaymentRequest ? $item->milestone->job : $item->job;
        $comment = $item->comments()->create(['author_type' => $actor->getMorphClass(), 'author_id' => $actor->getKey(), 'body' => trim($body)]);
        $this->log($job, $actor, 'comment.added', $comment);

        return $comment;
    }

    /** Plan storage limit (Free 5 GB, Solo 50 GB): checked before files are kept. */
    public function ensureStorage(Company $company, int $incomingBytes): void
    {
        $limit = $company->storageLimitBytes();
        if ($limit === null || $incomingBytes === 0) {
            return;
        }
        $this->ensure($company->storageUsedBytes() + $incomingBytes <= $limit,
            'Your :plan plan includes :gb GB of photo storage and it is full. Upgrade or remove unused proof to add more.',
            ['plan' => ucfirst($company->plan), 'gb' => intdiv($limit, 1024 ** 3)],
        );
    }

    /** @param array<string, mixed> $meta */
    public function log(Job $job, User|Client|null $actor, string $action, ?Model $subject = null, array $meta = []): void
    {
        ActivityLog::create([
            'company_id' => $job->company_id,
            'job_id' => $job->id,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()->ip(),
        ]);
        $this->notifier->notify($action, $job, $actor, $subject, $meta);
    }

    /**
     * @template T of Model
     *
     * @param  T  $model
     * @return T
     */
    private function lock(Model $model): Model
    {
        return $model->newQueryWithoutScopes()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Rule messages are English keys translated into the acting person's language (lang/*.json).
     *
     * @param  array<string, string|int>  $replace
     */
    private function ensure(bool $condition, string $message, array $replace = []): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['rule' => __($message, $replace)]);
        }
    }
}
