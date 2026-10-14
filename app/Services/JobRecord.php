<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\Proof;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** The full job history as a PDF: milestones with photo proof, variation orders, approvals and payments. */
class JobRecord
{
    /** Photos larger than this are listed by hash but not embedded, to keep PDF memory bounded. */
    private const MAX_EMBED_BYTES = 3 * 1024 * 1024;

    public function download(Job $job): Response
    {
        $job->load([
            'company', 'client',
            'milestones.proofs', 'milestones.paymentRequests',
            'variationOrders', 'payments.milestone',
        ]);

        $html = view('pdf.job-record', [
            'job' => $job,
            'summary' => $job->summary(),
            'photos' => $this->embeddablePhotos($job),
            // Read straight from the table so the record is identical whoever downloads it.
            'activity' => ActivityLog::withoutGlobalScopes()->where('job_id', $job->id)->oldest('id')->get(),
        ])->render();

        $filename = 'job-record-'.str($job->title)->slug().'-'.now()->format('Ymd').'.pdf';

        // Subsetting embeds only the glyphs used: essential for the Chinese font (~10 MB in full).
        return Pdf::loadHTML($html)->setOption('enable_font_subsetting', true)->setPaper('a4')->download($filename);
    }

    /**
     * Proof id => data URI for JPEG/PNG photos dompdf can draw (HEIC and oversized files are listed, not drawn).
     *
     * @return array<int, string>
     */
    private function embeddablePhotos(Job $job): array
    {
        $photos = [];
        foreach ($job->milestones as $milestone) {
            foreach ($milestone->proofs as $proof) {
                /** @var Proof $proof */
                $path = $proof->thumb_path ?? $proof->path;
                $mime = match (strtolower(pathinfo((string) $path, PATHINFO_EXTENSION))) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    default => null,
                };
                if ($proof->type !== 'photo' || ! $path || ! $mime || ! Storage::exists($path) || Storage::size($path) > self::MAX_EMBED_BYTES) {
                    continue;
                }
                $photos[$proof->id] = "data:{$mime};base64,".base64_encode((string) Storage::get($path));
            }
        }

        return $photos;
    }
}
