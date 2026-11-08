<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\PaymentRequest;
use App\Models\Proof;
use App\Services\JobFlow;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MilestoneController extends Controller
{
    public function __construct(private JobFlow $flow) {}

    /** @return array<string, mixed> */
    public function show(Milestone $milestone): array
    {
        $milestone->load(['proofs', 'paymentRequests', 'comments.author']);
        $milestone->proofs->each(fn (Proof $p) => $p->setAttribute('url', $p->path ? Storage::temporaryUrl($p->path, now()->addHour()) : null)
            ->setAttribute('thumb_url', ($p->thumb_path ?? $p->path) ? Storage::temporaryUrl((string) ($p->thumb_path ?? $p->path), now()->addHour()) : null));

        return [
            ...$milestone->withoutRelations()->toArray(),
            'proofs' => $milestone->proofs,
            'payment_requests' => $milestone->paymentRequests,
            'comments' => $milestone->comments->sortBy('id')->values()->map(fn (Comment $c) => $c->toThread()),
        ];
    }

    public function approveVerbally(Request $request, PaymentRequest $paymentRequest): PaymentRequest
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], [
            'note.required' => __('Note how and when the client approved.'),
        ]);

        return $this->flow->approveVerbally($paymentRequest, $request->user(), $data['note']);
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
            'client_uuid' => 'nullable|uuid',
        ]);

        // Offline queue retry of a proof the server already has: answer with it instead of adding a duplicate.
        $existing = isset($data['client_uuid']) ? $milestone->proofs()->where('client_uuid', $data['client_uuid'])->first() : null;
        if ($existing) {
            return $this->replayed($existing, $milestone, $data);
        }

        if ($data['type'] !== 'note') {
            // Only paths we issued for this milestone, and the stored bytes must match the device's hash.
            $valid = str_starts_with($data['path'], $this->proofPrefix($milestone)) && ! str_contains($data['path'], '..');
            if (! $valid || ! Storage::exists($data['path'])) {
                throw ValidationException::withMessages(['path' => __('Upload not found.')]);
            }
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, Storage::readStream($data['path']));
            if (! hash_equals(hash_final($ctx), strtolower($data['sha256']))) {
                throw ValidationException::withMessages(['sha256' => __('File hash does not match the uploaded file.')]);
            }
            $data['sha256'] = strtolower($data['sha256']);
        }

        try {
            return response()->json($this->flow->addProof($milestone, $request->user(), $data), 201);
        } catch (UniqueConstraintViolationException) {
            // Two retries raced; the other one saved it.
            return $this->replayed($milestone->proofs()->where('client_uuid', $data['client_uuid'])->firstOrFail(), $milestone, $data);
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

    /**
     * The proof this client_uuid already created (200, not 201). A re-uploaded copy of its file is deleted.
     *
     * @param  array<string, mixed>  $data
     */
    private function replayed(Proof $existing, Milestone $milestone, array $data): JsonResponse
    {
        $path = $data['path'] ?? null;
        if (is_string($path) && $path !== $existing->path && str_starts_with($path, $this->proofPrefix($milestone)) && ! str_contains($path, '..')) {
            Storage::delete($path);
        }

        return response()->json($existing, 200);
    }

    private function proofPrefix(Milestone $milestone): string
    {
        return "proofs/{$milestone->job->company_id}/{$milestone->id}/";
    }
}
