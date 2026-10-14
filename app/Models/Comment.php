<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $body
 * @property string $commentable_type
 * @property int $commentable_id
 * @property string $author_type
 * @property int $author_id
 * @property Carbon|null $created_at
 */
class Comment extends Model
{
    protected $guarded = ['id'];

    /** @return MorphTo<Model, $this> */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Shape used by both the contractor and client threads (load `author` first).
     *
     * @return array{id: int, body: string, from: 'client'|'contractor', author: string, created_at: string|null}
     */
    public function toThread(): array
    {
        $author = $this->author;

        return [
            'id' => $this->id,
            'body' => $this->body,
            'from' => $this->author_type === (new Client)->getMorphClass() ? 'client' : 'contractor',
            'author' => $author instanceof Client || $author instanceof User ? $author->name : '',
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
