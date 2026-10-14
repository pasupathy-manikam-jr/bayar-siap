<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\PaymentRequest;
use App\Models\VariationOrder;
use App\Services\JobFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/** Variation orders, payments, comments and push tokens. */
class MoneyController extends Controller
{
    public function __construct(private JobFlow $flow) {}

    public function storeVariationOrder(Request $request, Job $job): JsonResponse
    {
        $data = $request->validate([
            'description' => 'required|string|max:5000',
            'reason' => 'nullable|string|max:2000',
            'amount_cents' => 'required|integer|not_in:0',
            'milestone_id' => 'nullable|integer',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,heic,pdf|max:10240',
        ]);
        unset($data['attachments']);

        /** @var list<UploadedFile> $attachments */
        $attachments = $request->file('attachments', []);
        $vo = $this->flow->createVariationOrder($job, $request->user(), $data, $attachments);

        return response()->json($vo->load('attachments'), 201);
    }

    public function sendVariationOrder(Request $request, VariationOrder $variationOrder): VariationOrder
    {
        return $this->flow->sendVariationOrder($variationOrder, $request->user());
    }

    public function storePayment(Request $request, Job $job): JsonResponse
    {
        $data = $request->validate([
            'milestone_id' => 'nullable|integer',
            'amount_cents' => 'required|integer|min:1',
            'paid_on' => 'required|date|before_or_equal:today',
            'method' => 'required|in:transfer,cash,cheque,other',
            'reference' => 'nullable|string|max:255',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,webp,heic,pdf|max:10240',
        ]);
        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store("receipts/{$job->company_id}/{$job->id}");
        }
        unset($data['receipt']);

        return response()->json($this->flow->recordPayment($job, $request->user(), $data), 201);
    }

    public function storeComment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commentable_type' => 'required|in:milestone,payment_request,variation_order',
            'commentable_id' => 'required|integer',
            'body' => 'required|string|max:5000',
        ]);

        // Tenant scope on each model makes another company's item a 404.
        $id = (int) $data['commentable_id'];
        $item = match ($data['commentable_type']) {
            'milestone' => Milestone::findOrFail($id),
            'payment_request' => PaymentRequest::findOrFail($id),
            default => VariationOrder::findOrFail($id),
        };

        return response()->json($this->flow->comment($item, $request->user(), $data['body']), 201);
    }

    public function storeDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['platform' => 'required|in:android,ios', 'token' => 'required|string|max:500']);
        $device = $request->user()->deviceTokens()->updateOrCreate(['token' => $data['token']], $data + ['user_id' => $request->user()->id]);

        return response()->json($device, 201);
    }
}
