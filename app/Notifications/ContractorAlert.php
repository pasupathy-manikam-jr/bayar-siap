<?php

namespace App\Notifications;

use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Something the contractor should know about: kept in their notification list and pushed to their phone.
 * Title and body are English keys + placeholders; the list translates them on display, push at send time.
 */
class ContractorAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, string|int> $replace */
    public function __construct(
        public string $title,
        public string $body,
        public array $replace,
        public string $url,
        public int $jobId,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', PushChannel::class];
    }

    /** @return array{title: string, body: string, replace: array<string, string|int>, url: string, job_id: int} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'replace' => $this->replace, 'url' => $this->url, 'job_id' => $this->jobId];
    }

    /** In the recipient's language (Laravel sets their preferred locale while sending). */
    public function translatedTitle(): string
    {
        return __($this->title, $this->replace);
    }

    public function translatedBody(): string
    {
        return __($this->body, $this->replace);
    }
}
