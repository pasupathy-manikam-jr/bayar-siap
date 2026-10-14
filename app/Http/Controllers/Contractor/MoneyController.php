<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\VariationOrder;
use App\Services\JobFlow;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/** Variation orders and recorded payments, from the job page. */
class MoneyController extends Controller
{
    public function __construct(private JobFlow $flow) {}

    public function storeVariationOrder(Request $request, Job $job): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:extra,deduction'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'description' => ['required', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'milestone_id' => ['nullable', 'required_if:kind,deduction', 'integer'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
        ], ['milestone_id.required_if' => __('Choose the milestone this deduction comes off.')]);

        $cents = Money::toCents($data['amount']);
        /** @var list<UploadedFile> $attachments */
        $attachments = $request->file('attachments', []);
        $vo = $this->flow->createVariationOrder($job, $request->user(), [
            'description' => $data['description'],
            'reason' => $data['reason'] ?? null,
            'amount_cents' => $data['kind'] === 'deduction' ? -$cents : $cents,
            'milestone_id' => $data['milestone_id'] ?? null,
        ], $attachments);

        return $this->back($job, __(':number saved as draft. Send it when ready.', ['number' => $vo->number]));
    }

    public function sendVariationOrder(Request $request, VariationOrder $variationOrder): RedirectResponse
    {
        $this->flow->sendVariationOrder($variationOrder, $request->user());

        return $this->back($variationOrder->job, __(':number sent to the client to sign.', ['number' => $variationOrder->number]));
    }

    public function storePayment(Request $request, Job $job): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:transfer,cash,cheque,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'milestone_id' => ['nullable', 'integer'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
        ], [], ['paid_on' => __('payment date')]);

        $receipt = $request->file('receipt');
        $this->flow->recordPayment($job, $request->user(), [
            'amount_cents' => Money::toCents($data['amount']),
            'paid_on' => $data['paid_on'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'milestone_id' => $data['milestone_id'] ?? null,
            'receipt_path' => $receipt instanceof UploadedFile ? $receipt->store("receipts/{$job->company_id}/{$job->id}") : null,
        ]);

        return $this->back($job, __('Payment recorded. The client will be asked to confirm it.'));
    }

    private function back(Job $job, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('jobs.show', $job);
    }
}
