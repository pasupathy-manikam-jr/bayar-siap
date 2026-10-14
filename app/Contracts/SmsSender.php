<?php

namespace App\Contracts;

/** Sends a text message (SMS or WhatsApp) to a phone number. */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
