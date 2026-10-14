<?php

namespace App\Jobs;

use App\Models\Proof;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Writes a ~640px JPEG next to a photo proof. The original is never touched, so its SHA-256 still verifies.
 * Formats GD can't read (HEIC) are skipped; pages fall back to the original.
 */
class MakeProofThumbnail implements ShouldQueue
{
    use Queueable;

    public const MAX_EDGE = 640;

    public function __construct(public Proof $proof) {}

    public function handle(): void
    {
        $path = $this->proof->path;
        if ($this->proof->type !== 'photo' || ! $path || $this->proof->thumb_path || ! Storage::exists($path)) {
            return;
        }

        $bytes = (string) Storage::get($path);
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return;
        }

        $image = $this->upright($image, $bytes);
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $thumb = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        if ($thumb === false) {
            return;
        }

        ob_start();
        imagejpeg($thumb, null, 78);
        $thumbPath = preg_replace('/\.[^.\/]+$/', '', $path).'.thumb.jpg';
        Storage::put($thumbPath, (string) ob_get_clean());

        $this->proof->forceFill(['thumb_path' => $thumbPath])->save();
    }

    /** Phones store photos sideways and record the rotation in EXIF; bake it in so thumbnails display upright. */
    private function upright(\GdImage $image, string $bytes): \GdImage
    {
        $exif = str_starts_with($bytes, "\xFF\xD8") ? @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes)) : false;
        $angle = match (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
