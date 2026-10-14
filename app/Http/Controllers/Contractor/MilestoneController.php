<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\PaymentRequest;
use App\Models\Proof;
use App\Services\JobFlow;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MilestoneController extends Controller
{
    public function __construct(private JobFlow $flow) {}

    public function show(Milestone $milestone): Response
    {
        $milestone->load(['job.client', 'proofs', 'paymentRequests', 'comments.author']);

        return Inertia::render('milestones/show', [
            'job' => $milestone->job->only(['id', 'title', 'status']),
            'milestone' => [
                ...$milestone->only(['id', 'sequence', 'name', 'description', 'amount_cents', 'status']),
                'due_date' => $milestone->due_date?->toDateString(),
                'proofs_locked' => $milestone->proofsLocked(),
            ],
            'proofs' => $milestone->proofs->sortBy('captured_at')->values()->map(fn (Proof $p) => [
                ...$p->only(['id', 'type', 'caption', 'lat', 'lng', 'sha256']),
                'captured_at' => $p->captured_at?->toIso8601String(),
                'url' => $p->path ? Storage::temporaryUrl($p->path, now()->addHour()) : null,
                'thumb_url' => ($p->thumb_path ?? $p->path) ? Storage::temporaryUrl((string) ($p->thumb_path ?? $p->path), now()->addHour()) : null,
            ]),
            'requests' => $milestone->paymentRequests->map(fn (PaymentRequest $r) => [
                ...$r->only(['id', 'amount_cents', 'note', 'status', 'reject_reason', 'verbal_approval_note']),
                'created_at' => $r->created_at?->toIso8601String(),
                'decided_at' => $r->decided_at?->toIso8601String(),
            ]),
            'comments' => $milestone->comments->sortBy('id')->values()->map(fn (Comment $c) => $c->toThread()),
        ]);
    }

    public function start(Request $request, Milestone $milestone): RedirectResponse
    {
        $this->flow->startMilestone($milestone, $request->user());

        return $this->back($milestone, __('Milestone started. Add proof as the work progresses.'));
    }

    /** Photos and files uploaded from the browser; capture time and GPS come from EXIF when the photo has it. */
    public function storeProofs(Request $request, Milestone $milestone): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:15360'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ], ['files.required' => __('Choose at least one photo or file.')]);

        /** @var list<UploadedFile> $files */
        $files = $request->file('files');
        $this->flow->ensureStorage($milestone->job->company, array_sum(array_map(fn (UploadedFile $f) => (int) $f->getSize(), $files)));
        foreach ($files as $file) {
            $isPhoto = str_starts_with((string) $file->getMimeType(), 'image/');
            $path = $file->store("proofs/{$milestone->job->company_id}/{$milestone->id}");
            $this->flow->addProof($milestone, $request->user(), [
                'type' => $isPhoto ? 'photo' : 'file',
                'path' => $path,
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'caption' => $request->input('caption'),
                ...($isPhoto ? $this->exif($file) : ['captured_at' => now()]),
            ]);
        }

        return $this->back($milestone, trans_choice(':count file added.|:count files added.', count($files)));
    }

    public function storeNote(Request $request, Milestone $milestone): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->flow->addProof($milestone, $request->user(), ['type' => 'note', 'caption' => $data['note'], 'captured_at' => now()]);

        return $this->back($milestone, __('Note added.'));
    }

    public function destroyProof(Request $request, Proof $proof): RedirectResponse
    {
        $milestone = $proof->milestone;
        $this->flow->deleteProof($proof, $request->user());

        return $this->back($milestone, __('Proof removed.'));
    }

    public function requestPayment(Request $request, Milestone $milestone): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $this->flow->requestPayment($milestone, $request->user(), $data['note'] ?? null);

        return $this->back($milestone, __('Payment request sent to the client.'));
    }

    public function withdraw(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $this->flow->withdrawRequest($paymentRequest, $request->user());

        return $this->back($paymentRequest->milestone, __('Request withdrawn. You can edit the proof and request again.'));
    }

    public function approveVerbally(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], [
            'note.required' => __('Note how and when the client approved.'),
        ]);
        $this->flow->approveVerbally($paymentRequest, $request->user(), $data['note']);

        return $this->back($paymentRequest->milestone, __('Marked as approved verbally. The client has been told.'));
    }

    /** @return array{captured_at: CarbonInterface, lat?: float, lng?: float} */
    private function exif(UploadedFile $file): array
    {
        $exif = in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true) ? @exif_read_data($file->getRealPath()) : false;
        $taken = is_array($exif) && isset($exif['DateTimeOriginal']) ? Carbon::createFromFormat('Y:m:d H:i:s', (string) $exif['DateTimeOriginal']) : null;
        $result = ['captured_at' => $taken ?: now()];

        if (is_array($exif) && isset($exif['GPSLatitude'], $exif['GPSLongitude'], $exif['GPSLatitudeRef'], $exif['GPSLongitudeRef'])) {
            $result['lat'] = $this->gps($exif['GPSLatitude'], (string) $exif['GPSLatitudeRef']);
            $result['lng'] = $this->gps($exif['GPSLongitude'], (string) $exif['GPSLongitudeRef']);
        }

        return $result;
    }

    /** @param mixed $parts degrees/minutes/seconds as EXIF rationals ("3/1") */
    private function gps(mixed $parts, string $ref): float
    {
        $values = array_map(function ($rational) {
            [$num, $den] = array_pad(explode('/', (string) $rational), 2, '1');

            return (float) $den === 0.0 ? 0.0 : (float) $num / (float) $den;
        }, is_array($parts) ? array_values($parts) : []);
        [$deg, $min, $sec] = array_pad($values, 3, 0.0);
        $decimal = $deg + $min / 60 + $sec / 3600;

        return round(in_array($ref, ['S', 'W'], true) ? -$decimal : $decimal, 7);
    }

    private function back(Milestone $milestone, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('milestones.show', $milestone);
    }
}
