<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\PaymentRequest;
use App\Models\Proof;
use App\Services\JobFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MilestoneController extends Controller
{
    public function __construct(private JobFlow $flow) {}

    public function show(Milestone $milestone): Milestone
    {
        $milestone->load(['proofs', 'paymentRequests', 'comments.author']);
        $milestone->proofs->each(fn (Proof $p) => $p->setAttribute('url', $p->path ? Storage::temporaryUrl($p->path, now()->addHour()) : null)
            ->setAttribute('thumb_url', $p->thumb_path ? Storage::temporaryUrl($p->thumb_path, now()->addHour()) : null));

        return $milestone;
    }

    public function update(Request $request, Milestone $milestone): Milestone
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'amount_cents' => 'sometimes|required|integer|min:0',
            'due_date' => 'nullable|date',
        ]);

        return $this->flow->updateMilestone($milestone, $request->user(), $data);
    }

    public function start(Request $request, Milestone $milestone): Milestone
    {
        return $this->flow->startMilestone($milestone, $request->user());
    }

    /** Issue a short-lived pre-signed PUT URL; the app uploads straight to storage. */
    /** @return array<string, mixed> */
    public function uploadUrl(Request $request, Milestone $milestone): array
    {
        $data = $request->validate(['extension' => 'required|in:jpg,jpeg,png,webp,heic,pdf']);
        abort_if($milestone->proofsLocked(), 422, 'Proof is locked for this milestone.');

        $path = $this->proofPrefix($milestone).Str::uuid().'.'.$data['extension'];
        $upload = Storage::temporaryUploadUrl($path, now()->addMinutes(config('jobproof.proof_upload_url_minutes')));

        return ['path' => $path, 'url' => $upload['url'], 'headers' => $upload['headers']];
    }

    public function storeProof(Request $request, Milestone $milestone): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:photo,file,note',
            'path' => 'required_unless:type,note|nullable|string|max:255',
            'sha256' => 'required_unless:type,note|nullable|string|size:64',
            'captured_at' => 'required_if:type,photo|nullable|date',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'caption' => 'required_if:type,note|nullable|string|max:2000',
        ]);

        if ($data['type'] !== 'note') {
            // Only paths we issued for this milestone, and the stored bytes must match the device's hash.
            $valid = str_starts_with($data['path'], $this->proofPrefix($milestone)) && ! str_contains($data['path'], '..');
            if (! $valid || ! Storage::exists($data['path'])) {
                throw ValidationException::withMessages(['path' => 'Upload not found.']);
            }
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, Storage::readStream($data['path']));
            if (! hash_equals(hash_final($ctx), strtolower($data['sha256']))) {
                throw ValidationException::withMessages(['sha256' => 'File hash does not match the uploaded file.']);
            }
            $data['sha256'] = strtolower($data['sha256']);
        }

        try {
            return response()->json($this->flow->addProof($milestone, $request->user(), $data), 201);
        } catch (ValidationException $e) {
            // Already uploaded via the pre-signed URL; don't keep a file the job won't record (e.g. over the plan's storage).
            if ($data['type'] !== 'note') {
                Storage::delete($data['path']);
            }
            throw $e;
        }
    }

    public function destroyProof(Request $request, Proof $proof): Response
    {
        $this->flow->deleteProof($proof, $request->user());

        return response()->noContent();
    }

    public function requestPayment(Request $request, Milestone $milestone): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        return response()->json($this->flow->requestPayment($milestone, $request->user(), $data['note'] ?? null), 201);
    }

    public function withdraw(Request $request, PaymentRequest $paymentRequest): PaymentRequest
    {
        return $this->flow->withdrawRequest($paymentRequest, $request->user());
    }

    private function proofPrefix(Milestone $milestone): string
    {
        return "proofs/{$milestone->job->company_id}/{$milestone->id}/";
    }
}
