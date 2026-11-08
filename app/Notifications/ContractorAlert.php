<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Something the contractor should know about, kept in their notification list (web, and polled by the mobile app:
 * no Firebase push) and sent live over Reverb to whatever is open: event `notification` on
 * `private-App.Models.User.{id}`, same shape as an item of GET /api/v1/notifications.
 * Title and body are English keys + placeholders; the list translates them on display, push at send time.
 */
class ContractorAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|int>  $replace
     * @param  array{type?: string, id?: int}  $target  what it is about, so the app can open that screen
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $replace,
        public string $url,
        public int $jobId,
        public array $target = [],
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** Sent from inside the queued notification job, not queued a second time. */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage([
            ...$this->toArray($notifiable),
            'title' => $this->translatedTitle(),
            'body' => $this->translatedBody(),
            'unread' => true,
            'created_at' => now()->toIso8601String(),
        ]))->onConnection('sync');
    }

    /** Event name the clients bind to. */
    public function broadcastAs(): string
    {
        return 'notification';
    }

    /** @return array{title: string, body: string, replace: array<string, string|int>, url: string, job_id: int, target: array{type?: string, id?: int}} */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'replace' => $this->replace, 'url' => $this->url, 'job_id' => $this->jobId, 'target' => $this->target];
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
