<?php

namespace App\Models;

use App\Models\Concerns\ScopedThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $milestone_id
 * @property int $uploaded_by
 * @property string $type
 * @property string|null $path
 * @property string|null $thumb_path
 * @property string|null $sha256
 * @property int $bytes
 * @property Carbon|null $captured_at
 * @property string|null $caption
 * @property string|null $lat
 * @property string|null $lng
 * @property-read Milestone $milestone
 */
class Proof extends Model
{
    use ScopedThrough;

    public static function tenantParent(): string
    {
        return 'milestone';
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime'];
    }

    /** @return BelongsTo<Milestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * A photo as the job page and dashboard strips show it (load milestone.job first).
     *
     * @return array{id: int, thumb_url: string, caption: string|null, captured_at: string|null, milestone_id: int, milestone: string, job: string}
     */
    public function toStripItem(): array
    {
        return [
            'id' => $this->id,
            'thumb_url' => Storage::temporaryUrl((string) ($this->thumb_path ?? $this->path), now()->addHour()),
            'caption' => $this->caption,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'milestone_id' => $this->milestone_id,
            'milestone' => $this->milestone->name,
            'job' => $this->milestone->job->title,
        ];
    }
}
