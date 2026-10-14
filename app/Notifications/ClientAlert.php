<?php

namespace App\Notifications;

use App\Models\Client;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A text to the client with the link to act on, plus an email when one is on file; in the client's language. */
class ClientAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, string|int> $replace */
    public function __construct(
        public string $contractor,
        public string $message,
        public array $replace,
        public string $url,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(Client $notifiable): array
    {
        return $notifiable->email ? [SmsChannel::class, 'mail'] : [SmsChannel::class];
    }

    /** The message in the client's language (Laravel sets their preferred locale while sending). */
    public function text(): string
    {
        return __($this->message, $this->replace);
    }

    public function toSms(): string
    {
        return "{$this->contractor}: {$this->text()} {$this->url}";
    }

    public function toMail(Client $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->contractor}: {$this->text()}")
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line("{$this->contractor}: {$this->text()}")
            ->action(__('Open the job'), $this->url)
            ->line(__('You will be asked for a code sent to your phone.'));
    }
}
