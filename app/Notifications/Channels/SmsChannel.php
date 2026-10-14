<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsSender;
use App\Models\Client;
use App\Notifications\ClientAlert;

/** Texts the client's phone (SMS or WhatsApp, whatever SmsSender is bound to). */
class SmsChannel
{
    public function __construct(private SmsSender $sms) {}

    public function send(Client $notifiable, ClientAlert $notification): void
    {
        $this->sms->send($notifiable->phone, $notification->toSms());
    }
}
